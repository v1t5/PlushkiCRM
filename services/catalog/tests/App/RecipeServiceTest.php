<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\App;

use Plushki\Catalog\App\RecipeLineInput;
use Plushki\Catalog\App\RecipeService;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Tests\Fake\InMemoryIngredientRepo;
use Plushki\Catalog\Tests\Fake\InMemoryOutboxRepo;
use Plushki\Catalog\Tests\Fake\InMemoryProductRepo;
use Plushki\Catalog\Tests\Fake\InMemoryRecipeRepo;
use Plushki\Catalog\Tests\Fake\InMemoryUnitRepo;
use PHPUnit\Framework\TestCase;

final class RecipeServiceTest extends TestCase
{
    private InMemoryProductRepo $products;
    private InMemoryRecipeRepo $recipes;
    private InMemoryIngredientRepo $ingredients;
    private InMemoryUnitRepo $units;
    private InMemoryOutboxRepo $outbox;
    private RecipeService $svc;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepo();
        $this->recipes = new InMemoryRecipeRepo();
        $this->ingredients = new InMemoryIngredientRepo();
        $this->units = new InMemoryUnitRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new RecipeService(
            $this->products,
            $this->recipes,
            $this->ingredients,
            $this->units,
            $this->outbox,
        );
    }

    private function ts(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    private function seedProduct(string $id = 'p1', bool $archived = false): Product
    {
        $p = new Product(
            $id,
            'default',
            null,
            'SKU' . $id,
            'Name',
            '',
            100,
            $this->ts(),
            $this->ts(),
            $archived ? $this->ts() : null,
        );
        $this->products->add($p);

        return $p;
    }

    private function seedIngredient(string $id, bool $archived = false): Ingredient
    {
        $i = new Ingredient(
            $id,
            'default',
            strtoupper($id),
            'Ing ' . $id,
            'unit-g',
            0,
            $this->ts(),
            $this->ts(),
            $archived ? $this->ts() : null,
        );
        $this->ingredients->add($i);

        return $i;
    }

    private function seedUnit(string $id, int $factor = 1, bool $archived = false): Unit
    {
        $u = new Unit(
            $id,
            'default',
            'u' . $id,
            'Unit ' . $id,
            $factor === 1 ? null : 'base',
            $factor,
            $this->ts(),
            $this->ts(),
            $archived ? $this->ts() : null,
        );
        $this->units->add($u);

        return $u;
    }

    public function testGetByProductIdReturnsLines(): void
    {
        $this->seedProduct('p1');
        $line = RecipeLine::create('p1', 'ing-1', 'unit-1', 5);
        $this->recipes->setLines('p1', [$line]);

        self::assertSame([$line], $this->svc->getByProductId('p1'));
    }

    public function testGetByProductIdMissingProductThrows(): void
    {
        try {
            $this->svc->getByProductId('missing');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::ProductNotFound, $e->errorCode);
        }
    }

    public function testSetReplacesRecipeAndEmitsEventWithQtyInBase(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');
        $this->seedUnit('unit-kg', 1000); // 1 unit = 1000 base units

        $lines = $this->svc->set('p1', [new RecipeLineInput('ing-1', 'unit-kg', 3)]);

        self::assertCount(1, $lines);
        self::assertSame('ing-1', $lines[0]->ingredientId);
        self::assertSame(3, $lines[0]->qty);

        // recipe was replaced in the outbox repo
        self::assertArrayHasKey('p1', $this->outbox->recipes);
        self::assertCount(1, $this->outbox->recipes['p1']);

        $evt = $this->outbox->lastEvent();
        self::assertSame('catalog.v1.recipe_updated', $evt->schema);
        self::assertSame('product', $evt->aggregateType);
        self::assertSame('p1', $evt->aggregateId);
        // qty_in_base = qty (3) * unit.factor (1000) = 3000
        self::assertStringContainsString('"qty_in_base":3000', $evt->payload);
        self::assertStringContainsString('"unit_factor":1000', $evt->payload);
        self::assertStringContainsString('"ingredient_id":"ing-1"', $evt->payload);
    }

    public function testSetWithEmptyInputsClearsRecipe(): void
    {
        $this->seedProduct('p1');

        $lines = $this->svc->set('p1', []);

        self::assertSame([], $lines);
        self::assertSame([], $this->outbox->recipes['p1']);
        self::assertCount(1, $this->outbox->events, 'a recipe_updated event is still emitted');
    }

    public function testSetMultipleDistinctIngredients(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');
        $this->seedIngredient('ing-2');
        $this->seedUnit('unit-1', 1);

        $lines = $this->svc->set('p1', [
            new RecipeLineInput('ing-1', 'unit-1', 2),
            new RecipeLineInput('ing-2', 'unit-1', 4),
        ]);

        self::assertCount(2, $lines);
    }

    public function testSetDuplicateIngredientThrowsDuplicateRecipeLine(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');
        $this->seedUnit('unit-1', 1);

        try {
            $this->svc->set('p1', [
                new RecipeLineInput('ing-1', 'unit-1', 1),
                new RecipeLineInput('ing-1', 'unit-1', 2),
            ]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::DuplicateRecipeLine, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetOnArchivedProductThrowsProductArchived(): void
    {
        $this->seedProduct('p1', archived: true);

        try {
            $this->svc->set('p1', []);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::ProductArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetMissingProductThrowsProductNotFound(): void
    {
        try {
            $this->svc->set('missing', []);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::ProductNotFound, $e->errorCode);
        }
    }

    public function testSetMissingIngredientThrowsIngredientNotFound(): void
    {
        $this->seedProduct('p1');
        $this->seedUnit('unit-1', 1);

        try {
            $this->svc->set('p1', [new RecipeLineInput('missing', 'unit-1', 1)]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IngredientNotFound, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetArchivedIngredientThrowsIngredientArchived(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1', archived: true);
        $this->seedUnit('unit-1', 1);

        try {
            $this->svc->set('p1', [new RecipeLineInput('ing-1', 'unit-1', 1)]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IngredientArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetMissingUnitThrowsUnitNotFound(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');

        try {
            $this->svc->set('p1', [new RecipeLineInput('ing-1', 'missing', 1)]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitNotFound, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetArchivedUnitThrowsUnitArchived(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');
        $this->seedUnit('unit-1', 1000, archived: true);

        try {
            $this->svc->set('p1', [new RecipeLineInput('ing-1', 'unit-1', 1)]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testSetInvalidQtyThrowsInvalidQty(): void
    {
        $this->seedProduct('p1');
        $this->seedIngredient('ing-1');
        $this->seedUnit('unit-1', 1);

        try {
            $this->svc->set('p1', [new RecipeLineInput('ing-1', 'unit-1', 0)]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }
}

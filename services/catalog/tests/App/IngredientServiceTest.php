<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\App;

use Plushki\Catalog\App\IngredientService;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Tests\Fake\InMemoryIngredientRepo;
use Plushki\Catalog\Tests\Fake\InMemoryOutboxRepo;
use Plushki\Catalog\Tests\Fake\InMemoryUnitRepo;
use PHPUnit\Framework\TestCase;

final class IngredientServiceTest extends TestCase
{
    private InMemoryIngredientRepo $ingredients;
    private InMemoryUnitRepo $units;
    private InMemoryOutboxRepo $outbox;
    private IngredientService $svc;

    protected function setUp(): void
    {
        $this->ingredients = new InMemoryIngredientRepo();
        $this->units = new InMemoryUnitRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new IngredientService($this->ingredients, $this->units, $this->outbox);
    }

    private function ts(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    private function gram(): Unit
    {
        return new Unit('unit-g', 'default', 'g', 'Gram', null, 1, $this->ts(), $this->ts());
    }

    public function testCreateEmitsEventCarryingDefaultUnitCodeAndFactor(): void
    {
        $this->units->add($this->gram());

        $i = $this->svc->create('FLOUR', 'Flour', 'unit-g', 5000);

        self::assertSame('unit-g', $i->defaultUnitId);
        self::assertCount(1, $this->outbox->ingredients);
        $evt = $this->outbox->lastEvent();
        self::assertSame('catalog.v1.ingredient_created', $evt->schema);
        self::assertSame('ingredient', $evt->aggregateType);
        self::assertStringContainsString('"default_unit_code":"g"', $evt->payload);
        self::assertStringContainsString('"default_unit_factor":1', $evt->payload);
        self::assertStringContainsString('"low_stock_threshold_qty":5000', $evt->payload);
    }

    public function testCreateWithMissingUnitThrowsUnitNotFound(): void
    {
        try {
            $this->svc->create('FLOUR', 'Flour', 'missing', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitNotFound, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateWithArchivedUnitThrowsUnitArchived(): void
    {
        $archived = new Unit('unit-g', 'default', 'g', 'Gram', null, 1, $this->ts(), $this->ts(), $this->ts());
        $this->units->add($archived);

        try {
            $this->svc->create('FLOUR', 'Flour', 'unit-g', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateInvalidSkuThrowsBeforeOutbox(): void
    {
        $this->units->add($this->gram());

        try {
            $this->svc->create('bad sku', 'Flour', 'unit-g', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSKU, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testGetMissingThrowsIngredientNotFound(): void
    {
        try {
            $this->svc->get('nope');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IngredientNotFound, $e->errorCode);
        }
    }

    public function testListReturnsActiveIngredients(): void
    {
        $i = new Ingredient('i1', 'default', 'SKU', 'n', 'unit-g', 0, $this->ts(), $this->ts());
        $this->ingredients->add($i);

        self::assertSame([$i], $this->svc->list('default'));
        self::assertCount(1, $this->svc->list(''));
    }
}

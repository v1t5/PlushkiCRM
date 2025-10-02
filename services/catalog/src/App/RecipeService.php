<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\Envelope;
use Plushki\Catalog\Ports\IngredientRepo;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;
use Plushki\Catalog\Ports\ProductRepo;
use Plushki\Catalog\Ports\RecipeRepo;
use Plushki\Catalog\Ports\UnitRepo;

/**
 * Owns the bill-of-materials for a product. PUT /recipe is the only mutation: it
 * replaces the recipe wholesale and emits one catalog.v1.recipe_updated event
 * whose payload carries each ingredient's SKU/name and each unit's code/factor —
 * so inventory and production can translate qty into base units without calling back.
 */
final class RecipeService
{
    public function __construct(
        private readonly ProductRepo $products,
        private readonly RecipeRepo $recipes,
        private readonly IngredientRepo $ingredients,
        private readonly UnitRepo $units,
        private readonly OutboxRepo $outbox,
    ) {
    }

    /**
     * Returns the lines for a product. Empty array = no recipe.
     *
     * @return list<RecipeLine>
     */
    public function getByProductId(string $productId): array
    {
        $this->products->getById($productId); // existence check (throws if missing)

        return $this->recipes->listByProduct($productId);
    }

    /**
     * Wholesale-replaces the recipe lines for a product. An empty inputs array
     * clears the recipe. Validation: every ingredient/unit must exist and be
     * active; the same ingredient can appear at most once.
     *
     * @param list<RecipeLineInput> $inputs
     * @return list<RecipeLine>
     */
    public function set(string $productId, array $inputs): array
    {
        $product = $this->products->getById($productId);
        if ($product->isArchived()) {
            throw DomainException::of(ErrorCode::ProductArchived);
        }

        $resolved = $this->resolveLines($productId, $inputs);
        $lines = array_map(static fn (array $r): RecipeLine => $r['line'], $resolved);

        $this->outbox->replaceRecipe($productId, $lines, $this->recipeUpdatedEvent($product, $resolved));

        return $lines;
    }

    /**
     * @param list<RecipeLineInput> $inputs
     * @return list<array{line: RecipeLine, ingredient: Ingredient, unit: Unit}>
     */
    private function resolveLines(string $productId, array $inputs): array
    {
        $seen = [];
        $out = [];
        foreach ($inputs as $in) {
            if (isset($seen[$in->ingredientId])) {
                throw DomainException::of(ErrorCode::DuplicateRecipeLine);
            }
            $seen[$in->ingredientId] = true;

            $ing = $this->ingredients->getById($in->ingredientId);
            if ($ing->isArchived()) {
                throw DomainException::of(ErrorCode::IngredientArchived);
            }
            $unit = $this->units->getById($in->unitId);
            if ($unit->isArchived()) {
                throw DomainException::of(ErrorCode::UnitArchived);
            }
            $line = RecipeLine::create($productId, $in->ingredientId, $in->unitId, $in->qty);
            $out[] = ['line' => $line, 'ingredient' => $ing, 'unit' => $unit];
        }

        return $out;
    }

    /**
     * @param list<array{line: RecipeLine, ingredient: Ingredient, unit: Unit}> $resolved
     */
    private function recipeUpdatedEvent(Product $product, array $resolved): OutboxEvent
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $schema = 'catalog.v1.recipe_updated';

        $lineData = [];
        foreach ($resolved as $r) {
            /** @var RecipeLine $line */
            $line = $r['line'];
            /** @var Ingredient $ing */
            $ing = $r['ingredient'];
            /** @var Unit $unit */
            $unit = $r['unit'];
            // qty_in_base = qty * unit.factor — precomputed so consumers
            // (inventory, production) never need to call back to catalog.
            $lineData[] = [
                'ingredient_id' => $ing->id,
                'ingredient_sku' => $ing->sku,
                'ingredient_name' => $ing->name,
                'qty' => $line->qty,
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'unit_factor' => $unit->factor,
                'qty_in_base' => $line->qty * $unit->factor,
            ];
        }

        $env = Envelope::build(
            schema: $schema,
            data: [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'lines' => $lineData,
            ],
            actorType: 'system',
            actorId: 'catalog',
            occurredAt: $now->format('Y-m-d\TH:i:s.uP'),
            tenantId: $product->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $product->id,
            aggregateType: 'product',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $now,
            tenantId: $product->tenantId,
            traceId: $env->traceId,
        );
    }
}

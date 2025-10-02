<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Fake;

use Plushki\Inventory\Ports\IngredientProjection;
use Plushki\Inventory\Ports\IngredientProjectionRepo;

/**
 * Array-backed IngredientProjectionRepo. get() returns null for an unprojected
 * ingredient, matching the real adapter.
 */
final class InMemoryIngredientProjectionRepo implements IngredientProjectionRepo
{
    /** @var array<string, IngredientProjection> keyed by ingredientId */
    public array $byId = [];

    public function upsert(IngredientProjection $p): void
    {
        $this->byId[$p->ingredientId] = $p;
    }

    public function get(string $ingredientId): ?IngredientProjection
    {
        return $this->byId[$ingredientId] ?? null;
    }
}

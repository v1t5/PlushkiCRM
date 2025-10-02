<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Fake;

use Plushki\Production\Ports\RecipeProjection;
use Plushki\Production\Ports\RecipeProjectionRepo;

/**
 * Array-backed recipe projection cache. get returns null for unknown products,
 * matching the real adapter (no recipe yet → empty task_completed lines).
 */
final class FakeRecipeProjectionRepo implements RecipeProjectionRepo
{
    /** @var array<string, RecipeProjection> */
    private array $store = [];

    public function upsert(RecipeProjection $p): void
    {
        $this->store[$p->productId] = $p;
    }

    public function get(string $productId): ?RecipeProjection
    {
        return $this->store[$productId] ?? null;
    }
}

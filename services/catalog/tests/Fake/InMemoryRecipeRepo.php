<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Ports\RecipeRepo;

/** Array-backed RecipeRepo for usecase tests. */
final class InMemoryRecipeRepo implements RecipeRepo
{
    /** @var array<string, list<RecipeLine>> */
    public array $byProduct = [];

    /** @param list<RecipeLine> $lines */
    public function setLines(string $productId, array $lines): void
    {
        $this->byProduct[$productId] = $lines;
    }

    /** @return list<RecipeLine> */
    public function listByProduct(string $productId): array
    {
        return $this->byProduct[$productId] ?? [];
    }
}

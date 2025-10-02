<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\RecipeLine;

/** Persistence port for product recipes (BOM). */
interface RecipeRepo
{
    /** @return list<RecipeLine> */
    public function listByProduct(string $productId): array;
}

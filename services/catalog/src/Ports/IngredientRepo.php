<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\Ingredient;

/** Persistence port for ingredients. */
interface IngredientRepo
{
    public function getById(string $id): Ingredient;

    /** @return list<Ingredient> */
    public function listActive(string $tenantId): array;
}

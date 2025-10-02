<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

/**
 * IngredientProjectionRepo upserts/reads the local catalog ingredient cache —
 * a port of ports.IngredientProjectionRepo. Get returns null when the
 * ingredient hasn't been projected yet.
 */
interface IngredientProjectionRepo
{
    public function upsert(IngredientProjection $p): void;

    public function get(string $ingredientId): ?IngredientProjection;
}

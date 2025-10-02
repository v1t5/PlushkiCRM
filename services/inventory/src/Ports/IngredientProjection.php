<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

/**
 * IngredientProjection is the cached catalog metadata inventory needs for
 * low-stock comparisons (in base units) and stock_low/movement_posted payloads
 * without calling catalog at consume time — a port of ports.IngredientProjection.
 * The catalog.v1.ingredient_created consumer upserts these.
 */
final class IngredientProjection
{
    public function __construct(
        public string $ingredientId,
        public string $tenantId,
        public string $sku,
        public string $name,
        public string $defaultUnitCode,
        public int $defaultUnitFactor,
        public int $thresholdQtyInBase,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

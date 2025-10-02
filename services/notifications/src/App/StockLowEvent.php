<?php

declare(strict_types=1);

namespace Plushki\Notifications\App;

/**
 * The slice of an inventory.v1.stock_low envelope we consume. Quantities are in
 * base units (mg/ml/pcs); the body renderer scales back to the ingredient's
 * default unit using defaultUnitFactor. Value object: excluded from the service
 * container.
 */
final class StockLowEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $schema,
        public readonly string $subject,
        public readonly string $ingredientId,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $warehouseId,
        public readonly int $qtyInBase,
        public readonly int $thresholdQtyInBase,
        public readonly string $defaultUnitCode,
        public readonly int $defaultUnitFactor,
    ) {
    }
}

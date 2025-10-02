<?php

declare(strict_types=1);

namespace Plushki\Reporting\Ports;

/** Projector input for one inventory.v1.stock_low envelope. */
final class StockLowIn
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $ingredientId,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $warehouseId,
        public readonly int $thresholdQtyInBase,
        public readonly int $currentQtyInBase,
        public readonly string $defaultUnitCode,
        public readonly int $defaultUnitFactor,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}

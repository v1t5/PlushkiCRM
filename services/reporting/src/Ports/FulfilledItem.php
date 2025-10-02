<?php

declare(strict_types=1);

namespace Plushki\Reporting\Ports;

/**
 * One item line of an orders.v1.fulfilled envelope. Projected into top_items
 * per (tenant, day, product).
 */
final class FulfilledItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly int $qty,
        public readonly int $priceKopecks,
    ) {
    }
}

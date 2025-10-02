<?php

declare(strict_types=1);

namespace Plushki\Orders\Ports;

/**
 * CatalogProduct is the slice of a catalog response we snapshot into an order
 * line.
 */
final class CatalogProduct
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public int $priceKopecks,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

/**
 * Item is a snapshot line. Catalog state is captured at place time so a later
 * rename or price change on a product does not retroactively rewrite an order.
 */
final class Item
{
    public function __construct(
        public int $lineNo,
        public string $productId,
        public string $nameSnapshot,
        public string $skuSnapshot,
        public int $priceKopecksSnapshot,
        public int $qty,
    ) {
    }

    public function subtotalKopecks(): int
    {
        return $this->priceKopecksSnapshot * $this->qty;
    }
}

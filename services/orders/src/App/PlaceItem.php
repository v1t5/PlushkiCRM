<?php

declare(strict_types=1);

namespace Plushki\Orders\App;

/**
 * PlaceItem is what the caller hands to OrderService::place — a product id and
 * quantity. The service resolves the rest from catalog. Value object: excluded
 * from the service container.
 */
final class PlaceItem
{
    public function __construct(
        public readonly string $productId,
        public readonly int $qty,
    ) {
    }
}
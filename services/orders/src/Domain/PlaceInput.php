<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

/**
 * PlaceInput is the data needed to construct a freshly-placed order. Items are
 * already resolved against catalog by the app layer; the domain only knows
 * about snapshots.
 */
final class PlaceInput
{
    /** @param list<Item> $items */
    public function __construct(
        public Channel $channel,
        public string $customerRef,
        public array $items,
    ) {
    }
}

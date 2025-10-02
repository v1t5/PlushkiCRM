<?php

declare(strict_types=1);

namespace Plushki\Notifications\App;

/**
 * One line of an order event. Value object: excluded from the service container.
 */
final class OrderEventItem
{
    public function __construct(
        public readonly string $name,
        public readonly int $qty,
    ) {
    }
}

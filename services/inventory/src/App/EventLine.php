<?php

declare(strict_types=1);

namespace Plushki\Inventory\App;

use Plushki\Inventory\Domain\ItemKind;

/**
 * EventLine is one (item, qty) pair carried inside a consumer-driven batch
 * (orders.v1.fulfilled product lines, production.v1.task_completed ingredient
 * lines) — a port of app.EventLine. Qty is the magnitude in base units; the
 * service fixes the sign from the chosen MovementType. Value object.
 */
final class EventLine
{
    public function __construct(
        public readonly ItemKind $itemKind,
        public readonly string $itemId,
        public readonly int $qtyInBase,
    ) {
    }
}

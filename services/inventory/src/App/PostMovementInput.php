<?php

declare(strict_types=1);

namespace Plushki\Inventory\App;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;

/**
 * PostMovementInput is the parsed-from-HTTP form of a single manual movement —
 * a port of app.PostMovementInput. Value object: excluded from the container.
 */
final class PostMovementInput
{
    public function __construct(
        public readonly string $warehouseId,
        public readonly ItemKind $itemKind,
        public readonly string $itemId,
        public readonly MovementType $type,
        public readonly int $qtyInBase,
        public readonly string $reason,
    ) {
    }
}

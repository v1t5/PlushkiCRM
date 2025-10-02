<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

/**
 * StockLevel is the materialised running total for one (warehouse, item) — a
 * read model. Repositories build it; nothing constructs it from user input.
 */
final class StockLevel
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $warehouseId,
        public readonly ItemKind $itemKind,
        public readonly string $itemId,
        public readonly int $qtyInBase,
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}

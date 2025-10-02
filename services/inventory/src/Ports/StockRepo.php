<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;

/**
 * StockRepo reads the materialised running totals — a port of ports.StockRepo.
 */
interface StockRepo
{
    public function get(string $warehouseId, ItemKind $kind, string $itemId): StockLevel;

    /**
     * @return list<StockLevel>
     */
    public function list(string $tenantId, ?string $warehouseId, ?ItemKind $kind): array;
}

<?php

declare(strict_types=1);

namespace Plushki\Inventory\App;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Ports\StockRepo;

/**
 * StockService handles read-only queries against the materialised running
 * totals — a port of app.StockService.
 */
final class StockService
{
    public function __construct(private readonly StockRepo $stock)
    {
    }

    public function get(string $warehouseId, ItemKind $kind, string $itemId): StockLevel
    {
        return $this->stock->get($warehouseId, $kind, $itemId);
    }

    /** @return list<StockLevel> */
    public function list(string $tenantId, ?string $warehouseId, ?ItemKind $kind): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }

        return $this->stock->list($tenantId, $warehouseId, $kind);
    }
}

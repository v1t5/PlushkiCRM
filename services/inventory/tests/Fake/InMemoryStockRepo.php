<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Fake;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Ports\StockRepo;

/**
 * Array-backed StockRepo holding materialised running totals keyed by
 * (warehouse, kind, item). A miss returns a zero level.
 */
final class InMemoryStockRepo implements StockRepo
{
    /** @var array<string, StockLevel> */
    public array $levels = [];

    public function seed(StockLevel $l): void
    {
        $this->levels[self::key($l->warehouseId, $l->itemKind, $l->itemId)] = $l;
    }

    public function get(string $warehouseId, ItemKind $kind, string $itemId): StockLevel
    {
        return $this->levels[self::key($warehouseId, $kind, $itemId)]
            ?? new StockLevel('default', $warehouseId, $kind, $itemId, 0);
    }

    /** @return list<StockLevel> */
    public function list(string $tenantId, ?string $warehouseId, ?ItemKind $kind): array
    {
        $out = [];
        foreach ($this->levels as $l) {
            if ($l->tenantId !== $tenantId) {
                continue;
            }
            if ($warehouseId !== null && $l->warehouseId !== $warehouseId) {
                continue;
            }
            if ($kind !== null && $l->itemKind !== $kind) {
                continue;
            }
            $out[] = $l;
        }

        return $out;
    }

    private static function key(string $warehouseId, ItemKind $kind, string $itemId): string
    {
        return $warehouseId . '|' . $kind->value . '|' . $itemId;
    }
}

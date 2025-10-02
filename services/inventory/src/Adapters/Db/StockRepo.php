<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Ports\StockRepo as StockRepoPort;

/**
 * StockRepo is the DBAL-backed stock-level read adapter. A missing row reads
 * as zero stock at that slot.
 */
final class StockRepo implements StockRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function get(string $warehouseId, ItemKind $kind, string $itemId): StockLevel
    {
        $row = $this->db->fetchAssociative(
            'SELECT tenant_id, warehouse_id, item_kind, item_id, qty_in_base, updated_at
             FROM stock_levels
             WHERE warehouse_id = CAST(:wid AS uuid) AND item_kind = :kind AND item_id = CAST(:iid AS uuid)',
            ['wid' => $warehouseId, 'kind' => $kind->value, 'iid' => $itemId],
        );
        if ($row === false) {
            return new StockLevel('default', $warehouseId, $kind, $itemId, 0);
        }

        return self::map($row);
    }

    /** @return list<StockLevel> */
    public function list(string $tenantId, ?string $warehouseId, ?ItemKind $kind): array
    {
        $sql = 'SELECT tenant_id, warehouse_id, item_kind, item_id, qty_in_base, updated_at
                FROM stock_levels WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($warehouseId !== null) {
            $sql .= ' AND warehouse_id = CAST(:wid AS uuid)';
            $params['wid'] = $warehouseId;
        }
        if ($kind !== null) {
            $sql .= ' AND item_kind = :kind';
            $params['kind'] = $kind->value;
        }
        $sql .= ' ORDER BY warehouse_id, item_kind, item_id';

        $rows = $this->db->fetchAllAssociative($sql, $params);

        return array_map(self::map(...), $rows);
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): StockLevel
    {
        return new StockLevel(
            tenantId: (string) $r['tenant_id'],
            warehouseId: (string) $r['warehouse_id'],
            itemKind: ItemKind::from((string) $r['item_kind']),
            itemId: (string) $r['item_id'],
            qtyInBase: (int) $r['qty_in_base'],
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }
}

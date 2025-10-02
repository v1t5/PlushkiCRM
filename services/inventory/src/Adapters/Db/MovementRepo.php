<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\StockMovement;
use Plushki\Inventory\Ports\MovementRepo as MovementRepoPort;
use Plushki\Inventory\Ports\OutboxEvent;

/**
 * MovementRepo persists movements via DBAL. Each movement insert and its
 * running-total upsert (and any outbox rows) commit together. postBatch is
 * idempotent: a shared source_event_id that already exists is a no-op —
 * existing rows are returned and the outbox events are NOT re-written.
 *
 * pgsql aborts a transaction on the first failed statement, so we pre-check
 * existence before the batch and fall back to a catch on the (rare) concurrent
 * race rather than detecting the collision mid-transaction.
 */
final class MovementRepo implements MovementRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @param list<OutboxEvent> $evts
     * @return array{0: StockMovement, 1: StockLevel}
     */
    public function post(StockMovement $m, array $evts): array
    {
        return $this->db->transactional(function (Connection $tx) use ($m, $evts): array {
            [$mv, $lvl] = self::insertMovementAndUpdateLevel($tx, $m);
            foreach ($evts as $e) {
                OutboxRepo::insertInto($tx, $e);
            }

            return [$mv, $lvl];
        });
    }

    /**
     * @param list<StockMovement> $ms
     * @param list<OutboxEvent> $evts
     * @return array{0: list<StockMovement>, 1: list<StockLevel>, 2: bool}
     */
    public function postBatch(array $ms, array $evts): array
    {
        if ($ms === []) {
            return [[], [], false];
        }
        $src = $ms[0]->sourceEventId;

        // Idempotent redelivery: if any row for this source event already
        // exists, the batch was applied — return the stored rows, skip outbox.
        if ($src !== null && $this->existsBySource($src)) {
            return [...$this->fetchBatchBySource($src, $ms), true];
        }

        try {
            return $this->db->transactional(function (Connection $tx) use ($ms, $evts): array {
                $out = [];
                $levels = [];
                foreach ($ms as $m) {
                    [$mv, $lvl] = self::insertMovementAndUpdateLevel($tx, $m);
                    $out[] = $mv;
                    $levels[] = $lvl;
                }
                foreach ($evts as $e) {
                    OutboxRepo::insertInto($tx, $e);
                }

                return [$out, $levels, false];
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent double-delivery beat us to the unique index.
            if ($src !== null) {
                return [...$this->fetchBatchBySource($src, $ms), true];
            }
            throw new \RuntimeException('movement unique violation without source event');
        }
    }

    /** @return list<StockMovement> */
    public function listByItem(ItemKind $kind, string $itemId, int $limit): array
    {
        if ($limit <= 0 || $limit > 1000) {
            $limit = 100;
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, warehouse_id, item_kind, item_id, type, qty_in_base, reason, source_event_id, occurred_at, created_at
             FROM stock_movements
             WHERE item_kind = :kind AND item_id = CAST(:iid AS uuid)
             ORDER BY occurred_at DESC, id DESC
             LIMIT CAST(:limit AS integer)',
            ['kind' => $kind->value, 'iid' => $itemId, 'limit' => $limit],
        );

        return array_map(self::mapMovement(...), $rows);
    }

    private function existsBySource(string $eventId): bool
    {
        $found = $this->db->fetchOne(
            'SELECT 1 FROM stock_movements WHERE source_event_id = CAST(:eid AS uuid) LIMIT 1',
            ['eid' => $eventId],
        );

        return $found !== false;
    }

    /**
     * @param list<StockMovement> $ms
     * @return array{0: list<StockMovement>, 1: list<StockLevel>}
     */
    private function fetchBatchBySource(string $eventId, array $ms): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, warehouse_id, item_kind, item_id, type, qty_in_base, reason, source_event_id, occurred_at, created_at
             FROM stock_movements WHERE source_event_id = CAST(:eid AS uuid)
             ORDER BY occurred_at ASC',
            ['eid' => $eventId],
        );
        $movements = array_map(self::mapMovement(...), $rows);

        $levels = [];
        foreach ($ms as $m) {
            $row = $this->db->fetchAssociative(
                'SELECT tenant_id, warehouse_id, item_kind, item_id, qty_in_base, updated_at
                 FROM stock_levels
                 WHERE warehouse_id = CAST(:wid AS uuid) AND item_kind = :kind AND item_id = CAST(:iid AS uuid)',
                ['wid' => $m->warehouseId, 'kind' => $m->itemKind->value, 'iid' => $m->itemId],
            );
            if ($row !== false) {
                $levels[] = self::mapLevel($row);
            }
        }

        return [$movements, $levels];
    }

    /**
     * Inserts the movement and upserts the running total, returning both. The
     * level upsert adds the signed delta: qty_in_base = old + delta.
     *
     * @return array{0: StockMovement, 1: StockLevel}
     */
    private static function insertMovementAndUpdateLevel(Connection $tx, StockMovement $m): array
    {
        $tx->executeStatement(
            'INSERT INTO stock_movements
                (id, tenant_id, warehouse_id, item_kind, item_id, type, qty_in_base, reason, source_event_id, occurred_at, created_at)
             VALUES (CAST(:id AS uuid), :tenant_id, CAST(:wid AS uuid), :kind, CAST(:iid AS uuid), :type,
                     CAST(:qty AS bigint), :reason, CAST(:src AS uuid),
                     CAST(:occurred_at AS timestamptz), CAST(:created_at AS timestamptz))',
            [
                'id' => $m->id,
                'tenant_id' => $m->tenantId,
                'wid' => $m->warehouseId,
                'kind' => $m->itemKind->value,
                'iid' => $m->itemId,
                'type' => $m->type->value,
                'qty' => $m->qtyInBase,
                'reason' => $m->reason,
                'src' => $m->sourceEventId,
                'occurred_at' => Ts::fmt($m->occurredAt),
                'created_at' => Ts::fmt($m->createdAt),
            ],
        );

        $row = $tx->fetchAssociative(
            'INSERT INTO stock_levels (tenant_id, warehouse_id, item_kind, item_id, qty_in_base, updated_at)
             VALUES (:tenant_id, CAST(:wid AS uuid), :kind, CAST(:iid AS uuid), CAST(:qty AS bigint), CAST(:updated_at AS timestamptz))
             ON CONFLICT (warehouse_id, item_kind, item_id) DO UPDATE
                SET qty_in_base = stock_levels.qty_in_base + EXCLUDED.qty_in_base,
                    updated_at  = EXCLUDED.updated_at
             RETURNING tenant_id, warehouse_id, item_kind, item_id, qty_in_base, updated_at',
            [
                'tenant_id' => $m->tenantId,
                'wid' => $m->warehouseId,
                'kind' => $m->itemKind->value,
                'iid' => $m->itemId,
                'qty' => $m->qtyInBase,
                'updated_at' => Ts::fmt($m->occurredAt),
            ],
        );

        return [$m, self::mapLevel($row)];
    }

    /** @param array<string, mixed> $r */
    private static function mapMovement(array $r): StockMovement
    {
        return new StockMovement(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            warehouseId: (string) $r['warehouse_id'],
            itemKind: ItemKind::from((string) $r['item_kind']),
            itemId: (string) $r['item_id'],
            type: MovementType::from((string) $r['type']),
            qtyInBase: (int) $r['qty_in_base'],
            reason: (string) $r['reason'],
            sourceEventId: $r['source_event_id'] !== null ? (string) $r['source_event_id'] : null,
            occurredAt: Ts::parse((string) $r['occurred_at']),
            createdAt: Ts::parse((string) $r['created_at']),
        );
    }

    /** @param array<string, mixed> $r */
    private static function mapLevel(array $r): StockLevel
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

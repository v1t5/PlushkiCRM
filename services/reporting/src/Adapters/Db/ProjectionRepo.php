<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;
use Plushki\Reporting\Ports\FulfilledIn;
use Plushki\Reporting\Ports\MovementPostedIn;
use Plushki\Reporting\Ports\ProjectionRepo as ProjectionRepoPort;
use Plushki\Reporting\Ports\StockLowIn;

/**
 * DBAL-backed projection store. Each Apply* runs as one transaction guarded by a
 * shared applied_events idempotency row: a pre-check (and a catch on the
 * concurrent race) makes redelivery a no-op. "Waste percentage" =
 * WASTE / (WASTE+CONSUMED_BY_PRODUCTION+SOLD+OUT) in base units; deduction qtys
 * are stored signed, so we ABS at query time.
 */
final class ProjectionRepo implements ProjectionRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function applyFulfilled(FulfilledIn $in): bool
    {
        return $this->idempotent($in->eventId, 'orders.v1.fulfilled', function (Connection $tx) use ($in): void {
            $tx->executeStatement(
                'INSERT INTO sales_by_day (tenant_id, day, channel, order_count, revenue_kopecks, updated_at)
                 VALUES (:tenant, CAST(:day AS date), :channel, 1, CAST(:rev AS bigint), now())
                 ON CONFLICT (tenant_id, day, channel) DO UPDATE
                    SET order_count     = sales_by_day.order_count + 1,
                        revenue_kopecks = sales_by_day.revenue_kopecks + EXCLUDED.revenue_kopecks,
                        updated_at      = now()',
                ['tenant' => $in->tenantId, 'day' => $in->day->format('Y-m-d'), 'channel' => $in->channel, 'rev' => $in->totalKopecks],
            );
            foreach ($in->items as $it) {
                $tx->executeStatement(
                    'INSERT INTO top_items (tenant_id, day, product_id, sku, name, qty_sold, revenue_kopecks, updated_at)
                     VALUES (:tenant, CAST(:day AS date), CAST(:pid AS uuid), :sku, :name, CAST(:qty AS bigint), CAST(:rev AS bigint), now())
                     ON CONFLICT (tenant_id, day, product_id) DO UPDATE
                        SET qty_sold        = top_items.qty_sold + EXCLUDED.qty_sold,
                            revenue_kopecks = top_items.revenue_kopecks + EXCLUDED.revenue_kopecks,
                            sku             = EXCLUDED.sku,
                            name            = EXCLUDED.name,
                            updated_at      = now()',
                    [
                        'tenant' => $in->tenantId, 'day' => $in->day->format('Y-m-d'), 'pid' => $it->productId,
                        'sku' => $it->sku, 'name' => $it->name, 'qty' => $it->qty, 'rev' => $it->priceKopecks * $it->qty,
                    ],
                );
            }
        });
    }

    public function applyStockLow(StockLowIn $in): bool
    {
        return $this->idempotent($in->eventId, 'inventory.v1.stock_low', function (Connection $tx) use ($in): void {
            $tx->executeStatement(
                'INSERT INTO stock_low_events (
                    id, event_id, tenant_id, ingredient_id, sku, name, warehouse_id,
                    threshold_qty_in_base, current_qty_in_base, default_unit_code, default_unit_factor, occurred_at)
                 VALUES (CAST(:id AS uuid), CAST(:eid AS uuid), :tenant, CAST(:ing AS uuid), :sku, :name, CAST(:wh AS uuid),
                    CAST(:threshold AS bigint), CAST(:current AS bigint), :unit_code, CAST(:factor AS bigint), CAST(:occurred AS timestamptz))',
                [
                    'id' => Uuid::v7()->toRfc4122(),
                    'eid' => $in->eventId,
                    'tenant' => $in->tenantId,
                    'ing' => $in->ingredientId,
                    'sku' => $in->sku,
                    'name' => $in->name,
                    'wh' => $in->warehouseId,
                    'threshold' => $in->thresholdQtyInBase,
                    'current' => $in->currentQtyInBase,
                    'unit_code' => $in->defaultUnitCode,
                    'factor' => $in->defaultUnitFactor,
                    'occurred' => Ts::fmt($in->occurredAt),
                ],
            );
        });
    }

    public function applyMovementPosted(MovementPostedIn $in): bool
    {
        return $this->idempotent($in->eventId, 'inventory.v1.movement_posted', function (Connection $tx) use ($in): void {
            $tx->executeStatement(
                "INSERT INTO movements_by_day (
                    tenant_id, day, item_kind, item_id, type, item_sku, item_name, total_qty_in_base, updated_at)
                 VALUES (:tenant, CAST(:day AS date), :kind, CAST(:iid AS uuid), :type, :sku, :name, CAST(:qty AS bigint), now())
                 ON CONFLICT (tenant_id, day, item_kind, item_id, type) DO UPDATE
                    SET total_qty_in_base = movements_by_day.total_qty_in_base + EXCLUDED.total_qty_in_base,
                        item_sku  = CASE WHEN EXCLUDED.item_sku  <> '' THEN EXCLUDED.item_sku  ELSE movements_by_day.item_sku  END,
                        item_name = CASE WHEN EXCLUDED.item_name <> '' THEN EXCLUDED.item_name ELSE movements_by_day.item_name END,
                        updated_at = now()",
                [
                    'tenant' => $in->tenantId, 'day' => $in->day->format('Y-m-d'), 'kind' => $in->itemKind,
                    'iid' => $in->itemId, 'type' => $in->type, 'sku' => $in->itemSku, 'name' => $in->itemName, 'qty' => $in->qtyInBase,
                ],
            );
        });
    }

    /** @return list<array<string, mixed>> */
    public function listSalesDaily(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT day, SUM(order_count)::bigint AS order_count, SUM(revenue_kopecks)::bigint AS revenue_kopecks
             FROM sales_by_day WHERE tenant_id = :tenant AND day BETWEEN CAST(:from AS date) AND CAST(:to AS date)
             GROUP BY day ORDER BY day ASC',
            ['tenant' => $tenantId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        );

        return array_map(static fn (array $r): array => [
            'day' => self::dateStr($r['day']),
            'order_count' => (int) $r['order_count'],
            'revenue_kopecks' => (int) $r['revenue_kopecks'],
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listSalesByChannel(string $tenantId, \DateTimeImmutable $day): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT channel, order_count, revenue_kopecks FROM sales_by_day
             WHERE tenant_id = :tenant AND day = CAST(:day AS date) ORDER BY revenue_kopecks DESC',
            ['tenant' => $tenantId, 'day' => $day->format('Y-m-d')],
        );

        return array_map(static fn (array $r): array => [
            'channel' => (string) $r['channel'],
            'order_count' => (int) $r['order_count'],
            'revenue_kopecks' => (int) $r['revenue_kopecks'],
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listTopItems(string $tenantId, \DateTimeImmutable $day, int $limit, string $orderBy): array
    {
        if ($limit <= 0 || $limit > 100) {
            $limit = 10;
        }
        $column = strcasecmp($orderBy, 'revenue') === 0 ? 'revenue_kopecks' : 'qty_sold';
        $rows = $this->db->fetchAllAssociative(
            'SELECT product_id, sku, name, qty_sold, revenue_kopecks FROM top_items
             WHERE tenant_id = :tenant AND day = CAST(:day AS date)
             ORDER BY ' . $column . ' DESC, name ASC LIMIT CAST(:limit AS integer)',
            ['tenant' => $tenantId, 'day' => $day->format('Y-m-d'), 'limit' => $limit],
        );

        return array_map(static fn (array $r): array => [
            'product_id' => (string) $r['product_id'],
            'sku' => (string) $r['sku'],
            'name' => (string) $r['name'],
            'qty_sold' => (int) $r['qty_sold'],
            'revenue_kopecks' => (int) $r['revenue_kopecks'],
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listStockLowEvents(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, event_id, ingredient_id, sku, name, warehouse_id,
                    threshold_qty_in_base, current_qty_in_base, default_unit_code, default_unit_factor, occurred_at
             FROM stock_low_events
             WHERE tenant_id = :tenant AND occurred_at BETWEEN CAST(:from AS timestamptz) AND CAST(:to AS timestamptz)
             ORDER BY occurred_at DESC LIMIT CAST(:limit AS integer)',
            ['tenant' => $tenantId, 'from' => Ts::fmt($from), 'to' => Ts::fmt($to), 'limit' => $limit],
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'event_id' => (string) $r['event_id'],
            'ingredient_id' => (string) $r['ingredient_id'],
            'sku' => (string) $r['sku'],
            'name' => (string) $r['name'],
            'warehouse_id' => $r['warehouse_id'] !== null ? (string) $r['warehouse_id'] : '',
            'threshold_qty_in_base' => (int) $r['threshold_qty_in_base'],
            'current_qty_in_base' => (int) $r['current_qty_in_base'],
            'default_unit_code' => (string) $r['default_unit_code'],
            'default_unit_factor' => (int) $r['default_unit_factor'],
            'occurred_at' => Ts::rfc(Ts::parse((string) $r['occurred_at'])),
        ], $rows);
    }

    /** @return array{waste_qty_in_base: int, deduction_qty_in_base: int, percentage: float} */
    public function wasteSummary(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->db->fetchAssociative(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'WASTE' THEN ABS(total_qty_in_base) ELSE 0 END), 0)::bigint AS waste,
                COALESCE(SUM(CASE WHEN type IN ('WASTE','CONSUMED_BY_PRODUCTION','SOLD','OUT') THEN ABS(total_qty_in_base) ELSE 0 END), 0)::bigint AS deductions
             FROM movements_by_day
             WHERE tenant_id = :tenant AND day BETWEEN CAST(:from AS date) AND CAST(:to AS date)",
            ['tenant' => $tenantId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        );
        $waste = (int) ($row['waste'] ?? 0);
        $deductions = (int) ($row['deductions'] ?? 0);

        return [
            'waste_qty_in_base' => $waste,
            'deduction_qty_in_base' => $deductions,
            'percentage' => $deductions > 0 ? $waste / $deductions : 0.0,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listWaste(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }
        $rows = $this->db->fetchAllAssociative(
            "SELECT day, item_kind, item_id, item_sku, item_name, ABS(total_qty_in_base)::bigint AS waste
             FROM movements_by_day
             WHERE tenant_id = :tenant AND day BETWEEN CAST(:from AS date) AND CAST(:to AS date) AND type = 'WASTE'
             ORDER BY day DESC, ABS(total_qty_in_base) DESC LIMIT CAST(:limit AS integer)",
            ['tenant' => $tenantId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'limit' => $limit],
        );

        return array_map(static fn (array $r): array => [
            'day' => self::dateStr($r['day']),
            'item_kind' => (string) $r['item_kind'],
            'item_id' => (string) $r['item_id'],
            'item_sku' => (string) $r['item_sku'],
            'item_name' => (string) $r['item_name'],
            'waste_qty_in_base' => (int) $r['waste'],
        ], $rows);
    }

    /**
     * Shared idempotent-apply: pre-check applied_events, then run the projection
     * writes inside a transaction that also inserts the applied_events row. A
     * concurrent race surfaces as a unique violation → treated as already-applied.
     */
    private function idempotent(string $eventId, string $schema, callable $writes): bool
    {
        if ($this->alreadyApplied($eventId)) {
            return true;
        }
        try {
            $this->db->transactional(function (Connection $tx) use ($eventId, $schema, $writes): void {
                $tx->executeStatement(
                    'INSERT INTO applied_events (event_id, schema) VALUES (CAST(:eid AS uuid), :schema)',
                    ['eid' => $eventId, 'schema' => $schema],
                );
                $writes($tx);
            });
        } catch (UniqueConstraintViolationException) {
            return true;
        }

        return false;
    }

    private function alreadyApplied(string $eventId): bool
    {
        return $this->db->fetchOne(
            'SELECT 1 FROM applied_events WHERE event_id = CAST(:eid AS uuid) LIMIT 1',
            ['eid' => $eventId],
        ) !== false;
    }

    /** Postgres date column comes back as 'Y-m-d' (or with time) — normalise to Y-m-d. */
    private static function dateStr(mixed $v): string
    {
        return substr((string) $v, 0, 10);
    }
}

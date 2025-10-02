<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Platform\Events\OutboxRow;
use Plushki\Orders\Ports\OutboxEvent;
use Plushki\Orders\Ports\OutboxRepo as OutboxRepoPort;

/**
 * OutboxRepo persists orders and their outbox events. insertWithOrder writes
 * the order, all its items, and the orders.v1.placed event in one transaction;
 * insertWithStatusChange updates the status and writes the orders.v1.<status>
 * event in one transaction. fetchUnpublished/markPublished serve the relay.
 */
final class OutboxRepo implements OutboxRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insertWithOrder(Order $o, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($o, $evt): void {
            $tx->executeStatement(
                'INSERT INTO orders (id, tenant_id, channel, customer_ref, status, total_kopecks, created_at, updated_at)
                 VALUES (CAST(:id AS uuid), :tenant_id, :channel, :customer_ref, :status,
                         CAST(:total AS bigint), CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                [
                    'id' => $o->id,
                    'tenant_id' => $o->tenantId,
                    'channel' => $o->channel->value,
                    'customer_ref' => $o->customerRef,
                    'status' => $o->status->value,
                    'total' => $o->totalKopecks,
                    'created_at' => Ts::fmt($o->createdAt),
                    'updated_at' => Ts::fmt($o->updatedAt),
                ],
            );
            foreach ($o->items as $it) {
                $tx->executeStatement(
                    'INSERT INTO order_items (order_id, line_no, product_id, name_snapshot, sku_snapshot, price_kopecks_snapshot, qty)
                     VALUES (CAST(:order_id AS uuid), CAST(:line_no AS integer), CAST(:product_id AS uuid),
                             :name_snapshot, :sku_snapshot, CAST(:price AS bigint), CAST(:qty AS integer))',
                    [
                        'order_id' => $o->id,
                        'line_no' => $it->lineNo,
                        'product_id' => $it->productId,
                        'name_snapshot' => $it->nameSnapshot,
                        'sku_snapshot' => $it->skuSnapshot,
                        'price' => $it->priceKopecksSnapshot,
                        'qty' => $it->qty,
                    ],
                );
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function insertWithStatusChange(string $id, Status $status, \DateTimeImmutable $updatedAt, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($id, $status, $updatedAt, $evt): void {
            $affected = $tx->executeStatement(
                'UPDATE orders SET status = :status, updated_at = CAST(:at AS timestamptz) WHERE id = CAST(:id AS uuid)',
                ['id' => $id, 'status' => $status->value, 'at' => Ts::fmt($updatedAt)],
            );
            if ($affected === 0) {
                throw DomainException::of(ErrorCode::OrderNotFound);
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function fetchUnpublished(int $limit): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT event_id, schema, tenant_id, payload
             FROM outbox_events
             WHERE published_at IS NULL
             ORDER BY occurred_at ASC
             LIMIT CAST(:limit AS integer)',
            ['limit' => $limit],
        );

        return array_map(
            static fn (array $r): OutboxRow => new OutboxRow(
                eventId: (string) $r['event_id'],
                schema: (string) $r['schema'],
                tenantId: (string) $r['tenant_id'],
                payload: (string) $r['payload'],
            ),
            $rows,
        );
    }

    public function markPublished(array $eventIds, \DateTimeImmutable $at): void
    {
        if ($eventIds === []) {
            return;
        }
        $this->db->executeStatement(
            'UPDATE outbox_events SET published_at = CAST(:at AS timestamptz)
             WHERE event_id = ANY(CAST(:ids AS uuid[]))',
            ['at' => Ts::fmt($at), 'ids' => PgArray::encode($eventIds)],
        );
    }

    private function insertOutbox(Connection $tx, OutboxEvent $evt): void
    {
        $tx->executeStatement(
            'INSERT INTO outbox_events
                (event_id, aggregate_id, aggregate_type, schema, payload, occurred_at, tenant_id, trace_id)
             VALUES (CAST(:event_id AS uuid), CAST(:aggregate_id AS uuid), :aggregate_type, :schema,
                     CAST(:payload AS jsonb), CAST(:occurred_at AS timestamptz), :tenant_id, :trace_id)',
            [
                'event_id' => $evt->eventId,
                'aggregate_id' => $evt->aggregateId,
                'aggregate_type' => $evt->aggregateType,
                'schema' => $evt->schema,
                'payload' => $evt->payload,
                'occurred_at' => Ts::fmt($evt->occurredAt),
                'tenant_id' => $evt->tenantId,
                'trace_id' => $evt->traceId,
            ],
        );
    }
}
<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Production\Platform\Events\OutboxRow;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\OutboxRepo as OutboxRepoPort;

/**
 * DBAL outbox repository. insertInto is shared with PlanRepo/TaskRepo so the
 * plan_published / task_completed events are written inside the aggregate
 * transaction.
 */
final class OutboxRepo implements OutboxRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(OutboxEvent $evt): void
    {
        self::insertInto($this->db, $evt);
    }

    /** @return list<OutboxRow> */
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

    /** @param list<string> $eventIds */
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

    public static function insertInto(Connection $c, OutboxEvent $evt): void
    {
        $c->executeStatement(
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

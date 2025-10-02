<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Events\OutboxRow;
use Plushki\Identity\Ports\OutboxEvent;
use Plushki\Identity\Ports\OutboxRepo as OutboxRepoPort;

/**
 * OutboxRepo is the DBAL outbox implementation. insertWithUser writes the user
 * row and the user_created event in one transaction so the event cannot be
 * issued without the user, and the user cannot exist without the event queued.
 * fetchUnpublished/markPublished serve the relay.
 */
final class OutboxRepo implements OutboxRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insertWithUser(User $u, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($u, $evt): void {
            try {
                $tx->executeStatement(
                    'INSERT INTO users (id, tenant_id, email, password_hash, display_name, roles, created_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, :email, :password_hash, :display_name,
                             CAST(:roles AS text[]), CAST(:created_at AS timestamptz))',
                    [
                        'id' => $u->id,
                        'tenant_id' => $u->tenantId,
                        'email' => $u->email,
                        'password_hash' => $u->passwordHash,
                        'display_name' => $u->displayName,
                        'roles' => PgArray::encode($u->roles),
                        'created_at' => Ts::fmt($u->createdAt),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                throw DomainException::of(ErrorCode::EmailAlreadyTaken);
            }

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
        });
    }

    public function fetchUnpublished(int $limit): array
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT event_id, schema, tenant_id, payload
             FROM outbox_events
             WHERE published_at IS NULL
             ORDER BY occurred_at ASC
             LIMIT CAST(:limit AS integer)",
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
}

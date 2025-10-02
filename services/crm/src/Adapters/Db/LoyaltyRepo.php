<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\Loyalty;
use Plushki\Crm\Ports\LoyaltyRepo as LoyaltyRepoPort;
use Plushki\Crm\Ports\OutboxEvent;

/**
 * applyOrderFulfilled is idempotent via applied_order_events (event_id PK): a
 * redelivery refetches the existing loyalty and returns fresh=false without
 * re-writing the event.
 */
final class LoyaltyRepo implements LoyaltyRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function get(string $customerId): Loyalty
    {
        $row = $this->db->fetchAssociative(
            'SELECT customer_id, tenant_id, visit_count, total_kopecks, last_visit_at, updated_at
             FROM loyalty WHERE customer_id = CAST(:cid AS uuid)',
            ['cid' => $customerId],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::CustomerNotFound);
        }

        return self::map($row);
    }

    /**
     * @return array{0: Loyalty, 1: bool}
     */
    public function applyOrderFulfilled(
        string $eventId,
        string $customerId,
        string $orderId,
        int $totalKopecks,
        \DateTimeImmutable $occurredAt,
        OutboxEvent $evt,
    ): array {
        // Idempotency pre-check: already applied → no-op.
        if ($this->alreadyApplied($eventId)) {
            return [$this->get($customerId), false];
        }

        try {
            $loyalty = $this->db->transactional(function (Connection $tx) use ($eventId, $customerId, $orderId, $totalKopecks, $occurredAt, $evt): Loyalty {
                $tx->executeStatement(
                    'INSERT INTO applied_order_events (event_id, customer_id, order_id, total_kopecks, applied_at)
                     VALUES (CAST(:eid AS uuid), CAST(:cid AS uuid), CAST(:oid AS uuid), CAST(:total AS bigint), CAST(:at AS timestamptz))',
                    ['eid' => $eventId, 'cid' => $customerId, 'oid' => $orderId, 'total' => $totalKopecks, 'at' => Ts::fmt($occurredAt)],
                );
                $row = $tx->fetchAssociative(
                    'INSERT INTO loyalty (customer_id, tenant_id, visit_count, total_kopecks, last_visit_at, updated_at)
                     VALUES (CAST(:cid AS uuid), :tenant, 1, CAST(:total AS bigint), CAST(:at AS timestamptz), CAST(:at AS timestamptz))
                     ON CONFLICT (customer_id) DO UPDATE
                        SET visit_count   = loyalty.visit_count + 1,
                            total_kopecks = loyalty.total_kopecks + EXCLUDED.total_kopecks,
                            last_visit_at = EXCLUDED.last_visit_at,
                            updated_at    = EXCLUDED.updated_at
                     RETURNING customer_id, tenant_id, visit_count, total_kopecks, last_visit_at, updated_at',
                    ['cid' => $customerId, 'tenant' => $evt->tenantId, 'total' => $totalKopecks, 'at' => Ts::fmt($occurredAt)],
                );
                /** @var array<string, mixed> $row */
                OutboxRepo::insertInto($tx, $evt);

                return self::map($row);
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent double-delivery beat us to the event_id PK.
            return [$this->get($customerId), false];
        }

        return [$loyalty, true];
    }

    private function alreadyApplied(string $eventId): bool
    {
        return $this->db->fetchOne(
            'SELECT 1 FROM applied_order_events WHERE event_id = CAST(:eid AS uuid) LIMIT 1',
            ['eid' => $eventId],
        ) !== false;
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): Loyalty
    {
        return new Loyalty(
            customerId: (string) $r['customer_id'],
            tenantId: (string) $r['tenant_id'],
            visitCount: (int) $r['visit_count'],
            totalKopecks: (int) $r['total_kopecks'],
            lastVisitAt: $r['last_visit_at'] !== null ? Ts::parse((string) $r['last_visit_at']) : null,
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }
}

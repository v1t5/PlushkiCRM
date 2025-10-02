<?php

declare(strict_types=1);

namespace Plushki\Crm\Ports;

use Plushki\Crm\Domain\Loyalty;

/**
 * LoyaltyRepo manages loyalty totals + the orders.v1.fulfilled idempotency
 * table.
 */
interface LoyaltyRepo
{
    /** @throws \Plushki\Crm\Domain\DomainException CustomerNotFound */
    public function get(string $customerId): Loyalty;

    /**
     * Atomically: insert applied_order_events (event_id PK) — on conflict the
     * redelivery is a no-op (returns [loyalty, false]); else bump visit_count by
     * 1 and total by totalKopecks, and write the loyalty_updated outbox row.
     *
     * @return array{0: Loyalty, 1: bool} [loyalty, fresh]
     */
    public function applyOrderFulfilled(
        string $eventId,
        string $customerId,
        string $orderId,
        int $totalKopecks,
        \DateTimeImmutable $occurredAt,
        OutboxEvent $evt,
    ): array;
}

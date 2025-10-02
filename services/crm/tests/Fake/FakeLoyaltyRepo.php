<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Fake;

use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\Loyalty;
use Plushki\Crm\Ports\LoyaltyRepo;
use Plushki\Crm\Ports\OutboxEvent;

/**
 * Array-backed in-memory LoyaltyRepo mirroring the DB contract:
 * applyOrderFulfilled is idempotent on event_id (PK on applied_order_events).
 * A fresh apply bumps visit_count by 1 and total by totalKopecks and records the
 * inline outbox event; a redelivery is a no-op returning [loyalty, false].
 */
final class FakeLoyaltyRepo implements LoyaltyRepo
{
    /** @var array<string, Loyalty> keyed by customer id */
    public array $loyalty = [];

    /** @var array<string, true> applied event ids */
    public array $appliedEventIds = [];

    /** @var list<OutboxEvent> */
    public array $events = [];

    public function get(string $customerId): Loyalty
    {
        return $this->loyalty[$customerId]
            ?? throw DomainException::of(ErrorCode::CustomerNotFound);
    }

    public function applyOrderFulfilled(
        string $eventId,
        string $customerId,
        string $orderId,
        int $totalKopecks,
        \DateTimeImmutable $occurredAt,
        OutboxEvent $evt,
    ): array {
        $existing = $this->loyalty[$customerId] ?? null;

        if (isset($this->appliedEventIds[$eventId])) {
            // Redelivery — no-op. Existing totals (or a zeroed default) are returned.
            $loyalty = $existing ?? new Loyalty(
                $customerId,
                $evt->tenantId,
                0,
                0,
                null,
                $occurredAt,
            );

            return [$loyalty, false];
        }

        $this->appliedEventIds[$eventId] = true;

        $loyalty = new Loyalty(
            $customerId,
            $evt->tenantId,
            ($existing?->visitCount ?? 0) + 1,
            ($existing?->totalKopecks ?? 0) + $totalKopecks,
            $occurredAt,
            $occurredAt,
        );
        $this->loyalty[$customerId] = $loyalty;
        $this->events[] = $evt;

        return [$loyalty, true];
    }
}

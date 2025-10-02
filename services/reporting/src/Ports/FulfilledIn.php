<?php

declare(strict_types=1);

namespace Plushki\Reporting\Ports;

/**
 * Projector input for one orders.v1.fulfilled envelope. $day is truncated to a
 * UTC date.
 */
final class FulfilledIn
{
    /** @param list<FulfilledItem> $items */
    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly \DateTimeImmutable $day,
        public readonly string $channel,
        public readonly int $totalKopecks,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array $items,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Reporting\Ports;

/**
 * Projector input for one inventory.v1.movement_posted envelope. $qtyInBase is
 * signed exactly as carried in the event. $day is truncated to a UTC date.
 */
final class MovementPostedIn
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly \DateTimeImmutable $day,
        public readonly string $itemKind,
        public readonly string $itemId,
        public readonly string $itemSku,
        public readonly string $itemName,
        public readonly string $type,
        public readonly int $qtyInBase,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}

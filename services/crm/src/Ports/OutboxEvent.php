<?php

declare(strict_types=1);

namespace Plushki\Crm\Ports;

/**
 * OutboxEvent is the row stored in outbox_events. $payload is the full envelope
 * already serialised.
 */
final class OutboxEvent
{
    public function __construct(
        public string $eventId,
        public string $aggregateId,
        public string $aggregateType,
        public string $schema,
        public string $payload,
        public \DateTimeImmutable $occurredAt,
        public string $tenantId,
        public string $traceId,
    ) {
    }
}

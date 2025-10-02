<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

/**
 * The row stored in outbox_events. $payload is the full envelope (event_id,
 * occurred_at, tenant_id, trace_id, actor, schema, data) already serialised —
 * the relay publishes it as-is.
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

<?php

declare(strict_types=1);

namespace Plushki\Identity\Platform\Events;

/**
 * OutboxRow is one row of outbox_events as the relay sees it. Payload is the
 * already-serialised envelope JSON; the relay publishes it verbatim with
 * routing key `<schema>.<tenant_id>`.
 */
final class OutboxRow
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $schema,
        public readonly string $tenantId,
        public readonly string $payload,
    ) {
    }
}

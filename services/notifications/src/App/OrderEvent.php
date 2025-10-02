<?php

declare(strict_types=1);

namespace Plushki\Notifications\App;

/**
 * The subset of an orders.v1.* envelope we consume. Fields we don't care about
 * (actor, occurred_at) are dropped so a future schema bump doesn't blow up
 * parsing. Value object: excluded from the service container.
 */
final class OrderEvent
{
    /**
     * @param list<OrderEventItem> $items
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $schema,
        public readonly string $subject,
        public readonly string $orderId,
        public readonly string $status,
        public readonly string $customerRef,
        public readonly string $channel,
        public readonly array $items,
        public readonly int $total,
    ) {
    }
}

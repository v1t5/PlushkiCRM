<?php

declare(strict_types=1);

namespace Plushki\Crm\App;

/**
 * FulfilledInput carries the bits of orders.v1.fulfilled loyalty cares about.
 * The consumer translates the envelope into this.
 */
final class FulfilledInput
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $orderId,
        public readonly string $tenantId,
        public readonly string $customerRef,
        public readonly int $totalKopecks,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}

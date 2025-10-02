<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * Loyalty is the running totals for a customer. lastVisitAt is the most recently
 * applied fulfilled timestamp.
 */
final class Loyalty
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $tenantId,
        public readonly int $visitCount,
        public readonly int $totalKopecks,
        public readonly ?\DateTimeImmutable $lastVisitAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }
}

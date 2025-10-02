<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * Customer is the canonical record. displayName is best-effort cosmetic.
 */
final class Customer
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $displayName,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }
}

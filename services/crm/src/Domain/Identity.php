<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * Identity is one external handle bound to a customer. verifiedAt is currently
 * always null.
 */
final class Identity
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $customerId,
        public readonly IdentityType $type,
        public readonly string $value,
        public readonly ?\DateTimeImmutable $verifiedAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

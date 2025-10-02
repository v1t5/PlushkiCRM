<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Warehouse is a physical or logical bucket where stock is held. Soft-deleted
 * via archivedAt; never hard-deleted because stock_movements reference it.
 */
final class Warehouse
{
    private const CODE_RE = '/^[a-z0-9][a-z0-9_-]{0,31}$/';

    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $code,
        public readonly string $name,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    public static function create(string $code, string $name): self
    {
        $code = strtolower(trim($code));
        if (preg_match(self::CODE_RE, $code) !== 1) {
            throw DomainException::of(ErrorCode::InvalidCode);
        }
        $name = trim($name);
        if ($name === '') {
            throw DomainException::of(ErrorCode::InvalidName);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: 'default',
            code: $code,
            name: $name,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}

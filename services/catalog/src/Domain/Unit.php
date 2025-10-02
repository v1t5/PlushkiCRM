<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * A unit of measure. A unit is itself a base (baseUnitId === null and
 * factor === 1) or converts to a base via factor: 1 of this unit equals factor
 * base units. Recipes and stock movements use factor to translate a user-typed
 * qty into base units.
 */
final class Unit
{
    public const DEFAULT_TENANT = 'default';
    private const UNIT_CODE_RE = '/^[a-z][a-z0-9_]{0,31}$/';

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $baseUnitId,
        public int $factor,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    /**
     * Validates inputs and returns a Unit ready to insert.
     *   - For a base unit pass baseUnitId === null and factor === 1.
     *   - For a derived unit pass baseUnitId = <base.id> and factor > 1.
     * Higher layers must verify the referenced base exists and is itself a base.
     */
    public static function create(string $code, string $name, ?string $baseUnitId, int $factor): self
    {
        $code = strtolower(trim($code));
        if (preg_match(self::UNIT_CODE_RE, $code) !== 1) {
            throw DomainException::of(ErrorCode::InvalidUnitCode);
        }
        $name = trim($name);
        if ($name === '') {
            throw DomainException::of(ErrorCode::InvalidName);
        }
        if ($factor <= 0) {
            throw DomainException::of(ErrorCode::InvalidUnitFactor);
        }
        if ($baseUnitId === null && $factor !== 1) {
            throw DomainException::of(ErrorCode::InvalidUnitFactor);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            code: $code,
            name: $name,
            baseUnitId: $baseUnitId,
            factor: $factor,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /** Reports whether this unit is itself a base unit. */
    public function isBase(): bool
    {
        return $this->baseUnitId === null;
    }
}

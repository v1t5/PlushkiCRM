<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Groups products in the menu/admin UI. Phase 1 categories are flat (no
 * parent_id). Pure domain: no Symfony container, no SQL.
 */
final class Category
{
    public const DEFAULT_TENANT = 'default';
    private const SLUG_RE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public string $slug,
        public int $sortOrder,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    /** Validates inputs and returns a Category ready to insert. */
    public static function create(string $name, string $slug, int $sortOrder): self
    {
        $name = trim($name);
        if ($name === '') {
            throw DomainException::of(ErrorCode::InvalidName);
        }
        $slug = strtolower(trim($slug));
        if (preg_match(self::SLUG_RE, $slug) !== 1) {
            throw DomainException::of(ErrorCode::InvalidSlug);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            name: $name,
            slug: $slug,
            sortOrder: $sortOrder,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}

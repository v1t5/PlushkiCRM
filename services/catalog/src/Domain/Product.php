<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * A single sellable item. Price is int kopecks (never float, never decimal).
 * Description is optional (empty string, not NULL).
 */
final class Product
{
    public const DEFAULT_TENANT = 'default';
    private const SKU_RE = '/^[A-Z0-9][A-Z0-9_-]{0,63}$/';

    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $categoryId,
        public string $sku,
        public string $name,
        public string $description,
        public int $priceKopecks,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    /**
     * Validates inputs and returns a Product ready to insert. SKU is normalised
     * to upper-case so the unique index can't be tricked by case.
     */
    public static function create(string $sku, string $name, string $description, int $priceKopecks, ?string $categoryId): self
    {
        $sku = strtoupper(trim($sku));
        if (preg_match(self::SKU_RE, $sku) !== 1) {
            throw DomainException::of(ErrorCode::InvalidSKU);
        }
        $name = trim($name);
        if ($name === '') {
            throw DomainException::of(ErrorCode::InvalidName);
        }
        if ($priceKopecks < 0) {
            throw DomainException::of(ErrorCode::InvalidPrice);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            categoryId: $categoryId,
            sku: $sku,
            name: $name,
            description: trim($description),
            priceKopecks: $priceKopecks,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}

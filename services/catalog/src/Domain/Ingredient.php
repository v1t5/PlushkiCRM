<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * A raw input consumed by production. Stock lives in inventory; catalog owns
 * only the master record and the admin-facing low-stock threshold (in defaultUnit).
 */
final class Ingredient
{
    public const DEFAULT_TENANT = 'default';
    private const SKU_RE = '/^[A-Z0-9][A-Z0-9_-]{0,63}$/';

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $sku,
        public string $name,
        public string $defaultUnitId,
        public int $lowStockThresholdQty,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    /**
     * Validates inputs. defaultUnitId must reference an existing unit — checked
     * at the app layer.
     */
    public static function create(string $sku, string $name, string $defaultUnitId, int $lowStockThresholdQty): self
    {
        $sku = strtoupper(trim($sku));
        if (preg_match(self::SKU_RE, $sku) !== 1) {
            throw DomainException::of(ErrorCode::InvalidSKU);
        }
        $name = trim($name);
        if ($name === '') {
            throw DomainException::of(ErrorCode::InvalidName);
        }
        if ($defaultUnitId === '') {
            throw DomainException::of(ErrorCode::InvalidUnitRef);
        }
        if ($lowStockThresholdQty < 0) {
            throw DomainException::of(ErrorCode::InvalidThreshold);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            sku: $sku,
            name: $name,
            defaultUnitId: $defaultUnitId,
            lowStockThresholdQty: $lowStockThresholdQty,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}

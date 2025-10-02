<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * One (ingredient, qty, unit) entry on a product's bill of materials. Qty is in
 * the user-chosen Unit; consumers convert to base units via the unit's factor
 * (carried on catalog.v1.recipe_updated).
 */
final class RecipeLine
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $ingredientId,
        public int $qty,
        public string $unitId,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Validates a single line. The app layer must resolve and validate
     * ingredientId and unitId against persisted rows.
     */
    public static function create(string $productId, string $ingredientId, string $unitId, int $qty): self
    {
        if ($productId === '') {
            throw DomainException::of(ErrorCode::InvalidProductRef);
        }
        if ($ingredientId === '') {
            throw DomainException::of(ErrorCode::InvalidIngredientRef);
        }
        if ($unitId === '') {
            throw DomainException::of(ErrorCode::InvalidUnitRef);
        }
        if ($qty <= 0) {
            throw DomainException::of(ErrorCode::InvalidQty);
        }

        return new self(
            id: Uuid::v7()->toRfc4122(),
            productId: $productId,
            ingredientId: $ingredientId,
            qty: $qty,
            unitId: $unitId,
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}

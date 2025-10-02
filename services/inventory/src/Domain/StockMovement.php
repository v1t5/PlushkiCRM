<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * StockMovement is one append-only ledger row. Qty is *signed* in base units;
 * the running level in stock_levels is just SUM(qty_in_base) over
 * (warehouse, item_kind, item_id).
 *
 * sourceEventId is set for movements derived from upstream events
 * (orders.v1.fulfilled → SOLD; production.v1.task_completed →
 * CONSUMED_BY_PRODUCTION); the unique index on (source_event_id, item_kind,
 * item_id) makes redelivery a no-op at the DB layer.
 */
final class StockMovement
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $warehouseId,
        public readonly ItemKind $itemKind,
        public readonly string $itemId,
        public readonly MovementType $type,
        public readonly int $qtyInBase,
        public readonly string $reason,
        public readonly ?string $sourceEventId,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * create validates inputs and returns a movement ready to insert. Sign
     * rules: IN must be positive; OUT/WASTE/CONSUMED/SOLD must be negative;
     * ADJUST may be either but never zero.
     */
    public static function create(
        string $warehouseId,
        ItemKind $kind,
        string $itemId,
        MovementType $type,
        int $qtyInBase,
        string $reason,
        ?string $sourceEventId,
        ?\DateTimeImmutable $occurredAt,
    ): self {
        if ($warehouseId === '') {
            throw DomainException::of(ErrorCode::InvalidWarehouseRef);
        }
        if ($itemId === '') {
            throw DomainException::of(ErrorCode::InvalidItemRef);
        }
        if ($qtyInBase === 0) {
            throw DomainException::of(ErrorCode::InvalidQty);
        }
        switch ($type) {
            case MovementType::In:
                if ($qtyInBase < 0) {
                    throw DomainException::of(ErrorCode::InvalidQty);
                }
                break;
            case MovementType::Out:
            case MovementType::Waste:
            case MovementType::ConsumedByProduction:
            case MovementType::Sold:
                if ($qtyInBase > 0) {
                    throw DomainException::of(ErrorCode::InvalidQty);
                }
                break;
            default:
                // ADJUST may be either sign (already rejected zero above).
                break;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: 'default',
            warehouseId: $warehouseId,
            itemKind: $kind,
            itemId: $itemId,
            type: $type,
            qtyInBase: $qtyInBase,
            reason: trim($reason),
            sourceEventId: $sourceEventId,
            occurredAt: $occurredAt ?? $now,
            createdAt: $now,
        );
    }
}

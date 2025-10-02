<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http;

use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\StockMovement;
use Plushki\Inventory\Domain\Warehouse;

/**
 * Resp builds the JSON response bodies. Timestamps render RFC3339-with-offset.
 */
final class Resp
{
    private static function ts(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    /** @return array<string, mixed> */
    public static function warehouse(Warehouse $w): array
    {
        return [
            'id' => $w->id,
            'tenant_id' => $w->tenantId,
            'code' => $w->code,
            'name' => $w->name,
            'created_at' => self::ts($w->createdAt),
            'updated_at' => self::ts($w->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function movement(StockMovement $m): array
    {
        $out = [
            'id' => $m->id,
            'tenant_id' => $m->tenantId,
            'warehouse_id' => $m->warehouseId,
            'item_kind' => $m->itemKind->value,
            'item_id' => $m->itemId,
            'type' => $m->type->value,
            'qty_in_base' => $m->qtyInBase,
            'reason' => $m->reason,
            'occurred_at' => self::ts($m->occurredAt),
        ];
        if ($m->sourceEventId !== null) {
            $out['source_event_id'] = $m->sourceEventId;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function level(StockLevel $l): array
    {
        return [
            'warehouse_id' => $l->warehouseId,
            'item_kind' => $l->itemKind->value,
            'item_id' => $l->itemId,
            'qty_in_base' => $l->qtyInBase,
            'updated_at' => $l->updatedAt !== null ? self::ts($l->updatedAt) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Http;

use Plushki\Orders\Domain\Order;

/**
 * Resp builds the JSON response body. Timestamps are rendered
 * RFC3339-with-offset.
 */
final class Resp
{
    private static function ts(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    /** @return array<string, mixed> */
    public static function order(Order $o): array
    {
        $items = [];
        foreach ($o->items as $it) {
            $items[] = [
                'line_no' => $it->lineNo,
                'product_id' => $it->productId,
                'name' => $it->nameSnapshot,
                'sku' => $it->skuSnapshot,
                'price_kopecks' => $it->priceKopecksSnapshot,
                'qty' => $it->qty,
            ];
        }

        return [
            'id' => $o->id,
            'tenant_id' => $o->tenantId,
            'channel' => $o->channel->value,
            'customer_ref' => $o->customerRef,
            'status' => $o->status->value,
            'total_kopecks' => $o->totalKopecks,
            'items' => $items,
            'created_at' => self::ts($o->createdAt),
            'updated_at' => self::ts($o->updatedAt),
        ];
    }

    /**
     * @param list<Order> $orders
     * @return array<string, mixed>
     */
    public static function list(array $orders): array
    {
        return ['items' => array_map(self::order(...), $orders)];
    }
}
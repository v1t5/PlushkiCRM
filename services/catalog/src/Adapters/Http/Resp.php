<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Domain\Unit;

/**
 * Builds the JSON response bodies. Timestamps are rendered RFC3339-with-offset.
 */
final class Resp
{
    private static function ts(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    /** @return array<string, mixed> */
    public static function category(Category $c): array
    {
        return [
            'id' => $c->id,
            'tenant_id' => $c->tenantId,
            'name' => $c->name,
            'slug' => $c->slug,
            'sort_order' => $c->sortOrder,
            'created_at' => self::ts($c->createdAt),
            'updated_at' => self::ts($c->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function product(Product $p): array
    {
        $resp = [
            'id' => $p->id,
            'tenant_id' => $p->tenantId,
            'sku' => $p->sku,
            'name' => $p->name,
            'description' => $p->description,
            'price_kopecks' => $p->priceKopecks,
            'created_at' => self::ts($p->createdAt),
            'updated_at' => self::ts($p->updatedAt),
        ];
        if ($p->categoryId !== null) {
            $resp['category_id'] = $p->categoryId;
        }

        return $resp;
    }

    /** @return array<string, mixed> */
    public static function unit(Unit $u): array
    {
        $resp = [
            'id' => $u->id,
            'tenant_id' => $u->tenantId,
            'code' => $u->code,
            'name' => $u->name,
            'factor' => $u->factor,
            'created_at' => self::ts($u->createdAt),
            'updated_at' => self::ts($u->updatedAt),
        ];
        if ($u->baseUnitId !== null) {
            $resp['base_unit_id'] = $u->baseUnitId;
        }

        return $resp;
    }

    /** @return array<string, mixed> */
    public static function ingredient(Ingredient $i): array
    {
        return [
            'id' => $i->id,
            'tenant_id' => $i->tenantId,
            'sku' => $i->sku,
            'name' => $i->name,
            'default_unit_id' => $i->defaultUnitId,
            'low_stock_threshold_qty' => $i->lowStockThresholdQty,
            'created_at' => self::ts($i->createdAt),
            'updated_at' => self::ts($i->updatedAt),
        ];
    }

    /**
     * @param list<RecipeLine> $lines
     * @return array<string, mixed>
     */
    public static function recipe(string $productId, array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $out[] = [
                'id' => $l->id,
                'ingredient_id' => $l->ingredientId,
                'qty' => $l->qty,
                'unit_id' => $l->unitId,
            ];
        }

        return ['product_id' => $productId, 'lines' => $out];
    }
}
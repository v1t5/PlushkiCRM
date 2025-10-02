<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Production\Ports\RecipeLine;
use Plushki\Production\Ports\RecipeProjection;
use Plushki\Production\Ports\RecipeProjectionRepo as RecipeProjectionRepoPort;

/**
 * DBAL recipe-projection repository. Lines are stored as a JSONB array so
 * task_completed can attach the snapshot in one read.
 */
final class RecipeProjectionRepo implements RecipeProjectionRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function upsert(RecipeProjection $p): void
    {
        $lines = [];
        foreach ($p->lines as $l) {
            $lines[] = [
                'ingredient_id' => $l->ingredientId,
                'ingredient_sku' => $l->ingredientSku,
                'ingredient_name' => $l->ingredientName,
                'qty' => $l->qty,
                'unit_id' => $l->unitId,
                'unit_code' => $l->unitCode,
                'unit_factor' => $l->unitFactor,
                'qty_in_base' => $l->qtyInBase,
            ];
        }
        $this->db->executeStatement(
            'INSERT INTO recipe_projection (product_id, tenant_id, product_sku, lines, updated_at)
             VALUES (CAST(:product_id AS uuid), :tenant_id, :product_sku, CAST(:lines AS jsonb), CAST(:updated_at AS timestamptz))
             ON CONFLICT (product_id) DO UPDATE SET
                tenant_id   = EXCLUDED.tenant_id,
                product_sku = EXCLUDED.product_sku,
                lines       = EXCLUDED.lines,
                updated_at  = EXCLUDED.updated_at',
            [
                'product_id' => $p->productId,
                'tenant_id' => $p->tenantId,
                'product_sku' => $p->productSku,
                'lines' => json_encode($lines, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => Ts::fmt($p->updatedAt),
            ],
        );
    }

    public function get(string $productId): ?RecipeProjection
    {
        $row = $this->db->fetchAssociative(
            'SELECT product_id, tenant_id, product_sku, lines, updated_at
             FROM recipe_projection WHERE product_id = CAST(:product_id AS uuid)',
            ['product_id' => $productId],
        );
        if ($row === false) {
            return null;
        }
        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode((string) $row['lines'], true, 512, JSON_THROW_ON_ERROR) ?: [];
        $lines = [];
        foreach ($decoded as $l) {
            $lines[] = new RecipeLine(
                ingredientId: (string) ($l['ingredient_id'] ?? ''),
                ingredientSku: (string) ($l['ingredient_sku'] ?? ''),
                ingredientName: (string) ($l['ingredient_name'] ?? ''),
                qty: (int) ($l['qty'] ?? 0),
                unitId: (string) ($l['unit_id'] ?? ''),
                unitCode: (string) ($l['unit_code'] ?? ''),
                unitFactor: (int) ($l['unit_factor'] ?? 0),
                qtyInBase: (int) ($l['qty_in_base'] ?? 0),
            );
        }

        return new RecipeProjection(
            productId: (string) $row['product_id'],
            tenantId: (string) $row['tenant_id'],
            productSku: (string) $row['product_sku'],
            lines: $lines,
            updatedAt: Ts::parse((string) $row['updated_at']),
        );
    }
}

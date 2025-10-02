<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Inventory\Ports\IngredientProjection;
use Plushki\Inventory\Ports\IngredientProjectionRepo as IngredientProjectionRepoPort;

/**
 * IngredientProjectionRepo is the DBAL-backed ingredient projection adapter.
 * Upsert keyed on ingredient_id.
 */
final class IngredientProjectionRepo implements IngredientProjectionRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function upsert(IngredientProjection $p): void
    {
        $this->db->executeStatement(
            'INSERT INTO ingredient_projection
                (ingredient_id, tenant_id, sku, name, default_unit_code, default_unit_factor, threshold_qty_in_base, updated_at)
             VALUES (CAST(:id AS uuid), :tenant_id, :sku, :name, :unit_code,
                     CAST(:factor AS bigint), CAST(:threshold AS bigint), CAST(:updated_at AS timestamptz))
             ON CONFLICT (ingredient_id) DO UPDATE SET
                tenant_id             = EXCLUDED.tenant_id,
                sku                   = EXCLUDED.sku,
                name                  = EXCLUDED.name,
                default_unit_code     = EXCLUDED.default_unit_code,
                default_unit_factor   = EXCLUDED.default_unit_factor,
                threshold_qty_in_base = EXCLUDED.threshold_qty_in_base,
                updated_at            = EXCLUDED.updated_at',
            [
                'id' => $p->ingredientId,
                'tenant_id' => $p->tenantId,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit_code' => $p->defaultUnitCode,
                'factor' => $p->defaultUnitFactor,
                'threshold' => $p->thresholdQtyInBase,
                'updated_at' => Ts::fmt($p->updatedAt),
            ],
        );
    }

    public function get(string $ingredientId): ?IngredientProjection
    {
        $row = $this->db->fetchAssociative(
            'SELECT ingredient_id, tenant_id, sku, name, default_unit_code, default_unit_factor, threshold_qty_in_base, updated_at
             FROM ingredient_projection WHERE ingredient_id = CAST(:id AS uuid)',
            ['id' => $ingredientId],
        );
        if ($row === false) {
            return null;
        }

        return new IngredientProjection(
            ingredientId: (string) $row['ingredient_id'],
            tenantId: (string) $row['tenant_id'],
            sku: (string) $row['sku'],
            name: (string) $row['name'],
            defaultUnitCode: (string) $row['default_unit_code'],
            defaultUnitFactor: (int) $row['default_unit_factor'],
            thresholdQtyInBase: (int) $row['threshold_qty_in_base'],
            updatedAt: Ts::parse((string) $row['updated_at']),
        );
    }
}

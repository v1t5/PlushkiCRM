<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Ports\IngredientRepo as IngredientRepoPort;

/** DBAL implementation of the ingredient persistence port. */
final class IngredientRepo implements IngredientRepoPort
{
    private const COLS = 'id, tenant_id, sku, name, default_unit_id, low_stock_threshold_qty, created_at, updated_at, archived_at';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Ingredient
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM ingredients WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::IngredientNotFound);
        }

        return self::scan($row);
    }

    public function listActive(string $tenantId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT ' . self::COLS . ' FROM ingredients
             WHERE tenant_id = :tenant AND archived_at IS NULL
             ORDER BY name ASC',
            ['tenant' => $tenantId],
        );

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): Ingredient
    {
        return new Ingredient(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            sku: (string) $row['sku'],
            name: (string) $row['name'],
            defaultUnitId: (string) $row['default_unit_id'],
            lowStockThresholdQty: (int) $row['low_stock_threshold_qty'],
            createdAt: Ts::parse((string) $row['created_at']),
            updatedAt: Ts::parse((string) $row['updated_at']),
            archivedAt: $row['archived_at'] !== null ? Ts::parse((string) $row['archived_at']) : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Ports\ProductRepo as ProductRepoPort;

/** DBAL implementation of the product persistence port. */
final class ProductRepo implements ProductRepoPort
{
    private const COLS = 'id, tenant_id, category_id, sku, name, description, price_kopecks, created_at, updated_at, archived_at';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Product
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM products WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::ProductNotFound);
        }

        return self::scan($row);
    }

    public function listActive(string $tenantId, ?string $categoryId): array
    {
        if ($categoryId !== null) {
            $rows = $this->db->fetchAllAssociative(
                'SELECT ' . self::COLS . ' FROM products
                 WHERE tenant_id = :tenant AND archived_at IS NULL AND category_id = CAST(:cat AS uuid)
                 ORDER BY name ASC',
                ['tenant' => $tenantId, 'cat' => $categoryId],
            );
        } else {
            $rows = $this->db->fetchAllAssociative(
                'SELECT ' . self::COLS . ' FROM products
                 WHERE tenant_id = :tenant AND archived_at IS NULL
                 ORDER BY name ASC',
                ['tenant' => $tenantId],
            );
        }

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): Product
    {
        return new Product(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            categoryId: $row['category_id'] !== null ? (string) $row['category_id'] : null,
            sku: (string) $row['sku'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            priceKopecks: (int) $row['price_kopecks'],
            createdAt: Ts::parse((string) $row['created_at']),
            updatedAt: Ts::parse((string) $row['updated_at']),
            archivedAt: $row['archived_at'] !== null ? Ts::parse((string) $row['archived_at']) : null,
        );
    }
}

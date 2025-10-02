<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Ports\CategoryRepo as CategoryRepoPort;

/** DBAL implementation of the category persistence port. Hand-written SQL, no ORM. */
final class CategoryRepo implements CategoryRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Category
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, name, slug, sort_order, created_at, updated_at, archived_at
             FROM categories WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::CategoryNotFound);
        }

        return self::scan($row);
    }

    public function listActive(string $tenantId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, name, slug, sort_order, created_at, updated_at, archived_at
             FROM categories
             WHERE tenant_id = :tenant AND archived_at IS NULL
             ORDER BY sort_order ASC, name ASC',
            ['tenant' => $tenantId],
        );

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): Category
    {
        return new Category(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            sortOrder: (int) $row['sort_order'],
            createdAt: Ts::parse((string) $row['created_at']),
            updatedAt: Ts::parse((string) $row['updated_at']),
            archivedAt: $row['archived_at'] !== null ? Ts::parse((string) $row['archived_at']) : null,
        );
    }
}

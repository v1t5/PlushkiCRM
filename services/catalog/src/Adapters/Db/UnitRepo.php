<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Ports\UnitRepo as UnitRepoPort;

/** DBAL implementation of the unit persistence port. */
final class UnitRepo implements UnitRepoPort
{
    private const COLS = 'id, tenant_id, code, name, base_unit_id, factor, created_at, updated_at, archived_at';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Unit
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM units WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::UnitNotFound);
        }

        return self::scan($row);
    }

    public function getByCode(string $tenantId, string $code): Unit
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM units
             WHERE tenant_id = :tenant AND code = :code AND archived_at IS NULL',
            ['tenant' => $tenantId, 'code' => $code],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::UnitNotFound);
        }

        return self::scan($row);
    }

    public function listActive(string $tenantId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT ' . self::COLS . ' FROM units
             WHERE tenant_id = :tenant AND archived_at IS NULL
             ORDER BY code ASC',
            ['tenant' => $tenantId],
        );

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): Unit
    {
        return new Unit(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            baseUnitId: $row['base_unit_id'] !== null ? (string) $row['base_unit_id'] : null,
            factor: (int) $row['factor'],
            createdAt: Ts::parse((string) $row['created_at']),
            updatedAt: Ts::parse((string) $row['updated_at']),
            archivedAt: $row['archived_at'] !== null ? Ts::parse((string) $row['archived_at']) : null,
        );
    }
}

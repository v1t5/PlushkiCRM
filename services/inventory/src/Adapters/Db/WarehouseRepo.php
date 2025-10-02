<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\Warehouse;
use Plushki\Inventory\Ports\WarehouseRepo as WarehouseRepoPort;

/**
 * WarehouseRepo is the DBAL-backed warehouse persistence adapter.
 */
final class WarehouseRepo implements WarehouseRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(Warehouse $w): void
    {
        try {
            $this->db->executeStatement(
                'INSERT INTO warehouses (id, tenant_id, code, name, created_at, updated_at)
                 VALUES (CAST(:id AS uuid), :tenant_id, :code, :name,
                         CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                [
                    'id' => $w->id,
                    'tenant_id' => $w->tenantId,
                    'code' => $w->code,
                    'name' => $w->name,
                    'created_at' => Ts::fmt($w->createdAt),
                    'updated_at' => Ts::fmt($w->updatedAt),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::CodeAlreadyTaken);
        }
    }

    public function getById(string $id): Warehouse
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, code, name, created_at, updated_at, archived_at
             FROM warehouses WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::WarehouseNotFound);
        }

        return self::map($row);
    }

    public function getByCode(string $tenantId, string $code): Warehouse
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, code, name, created_at, updated_at, archived_at
             FROM warehouses WHERE tenant_id = :tenant_id AND code = :code AND archived_at IS NULL',
            ['tenant_id' => $tenantId, 'code' => $code],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::WarehouseNotFound);
        }

        return self::map($row);
    }

    /** @return list<Warehouse> */
    public function listActive(string $tenantId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, code, name, created_at, updated_at, archived_at
             FROM warehouses WHERE tenant_id = :tenant_id AND archived_at IS NULL
             ORDER BY code ASC',
            ['tenant_id' => $tenantId],
        );

        return array_map(self::map(...), $rows);
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): Warehouse
    {
        return new Warehouse(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            code: (string) $r['code'],
            name: (string) $r['name'],
            createdAt: Ts::parse((string) $r['created_at']),
            updatedAt: Ts::parse((string) $r['updated_at']),
            archivedAt: $r['archived_at'] !== null ? Ts::parse((string) $r['archived_at']) : null,
        );
    }
}

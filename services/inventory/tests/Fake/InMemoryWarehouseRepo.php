<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Fake;

use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\Warehouse;
use Plushki\Inventory\Ports\WarehouseRepo;

/**
 * Array-backed WarehouseRepo. Mirrors the DB adapter contract: getById/getByCode
 * throw DomainException(WarehouseNotFound) on a miss, insert throws
 * CodeAlreadyTaken on a duplicate (tenant, code).
 */
final class InMemoryWarehouseRepo implements WarehouseRepo
{
    /** @var array<string, Warehouse> keyed by id */
    public array $byId = [];

    public function insert(Warehouse $w): void
    {
        foreach ($this->byId as $existing) {
            if ($existing->tenantId === $w->tenantId && $existing->code === $w->code) {
                throw DomainException::of(ErrorCode::CodeAlreadyTaken);
            }
        }
        $this->byId[$w->id] = $w;
    }

    public function getById(string $id): Warehouse
    {
        return $this->byId[$id] ?? throw DomainException::of(ErrorCode::WarehouseNotFound);
    }

    public function getByCode(string $tenantId, string $code): Warehouse
    {
        foreach ($this->byId as $w) {
            if ($w->tenantId === $tenantId && $w->code === $code) {
                return $w;
            }
        }

        throw DomainException::of(ErrorCode::WarehouseNotFound);
    }

    /** @return list<Warehouse> */
    public function listActive(string $tenantId): array
    {
        $out = [];
        foreach ($this->byId as $w) {
            if ($w->tenantId === $tenantId && !$w->isArchived()) {
                $out[] = $w;
            }
        }

        return $out;
    }
}

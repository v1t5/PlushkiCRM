<?php

declare(strict_types=1);

namespace Plushki\Inventory\App;

use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\Warehouse;
use Plushki\Inventory\Ports\WarehouseRepo;

/**
 * WarehouseService manages the master list of warehouses. Create emits no domain
 * event (no consumer for it yet). ensureDefault is race-safe for the
 * multi-container split: each worker resolves the warehouse independently.
 */
final class WarehouseService
{
    public function __construct(private readonly WarehouseRepo $repo)
    {
    }

    public function create(string $code, string $name): Warehouse
    {
        $w = Warehouse::create($code, $name);
        $this->repo->insert($w);

        return $w;
    }

    public function get(string $id): Warehouse
    {
        return $this->repo->getById($id);
    }

    /** @return list<Warehouse> */
    public function list(string $tenantId): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }

        return $this->repo->listActive($tenantId);
    }

    /**
     * ensureDefault reads the warehouse with $code; if missing it creates it.
     * Race-safe: a concurrent creator that wins the unique index surfaces as
     * CodeAlreadyTaken, in which case we re-read and adopt the existing row.
     */
    public function ensureDefault(string $code, string $name): Warehouse
    {
        try {
            return $this->repo->getByCode('default', $code);
        } catch (DomainException $e) {
            if ($e->errorCode !== ErrorCode::WarehouseNotFound) {
                throw $e;
            }
        }
        try {
            return $this->create($code, $name);
        } catch (DomainException $e) {
            if ($e->errorCode === ErrorCode::CodeAlreadyTaken) {
                return $this->repo->getByCode('default', $code);
            }
            throw $e;
        }
    }
}

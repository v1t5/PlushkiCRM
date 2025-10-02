<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

use Plushki\Inventory\Domain\Warehouse;

/**
 * WarehouseRepo is the persistence port for warehouses.
 */
interface WarehouseRepo
{
    public function insert(Warehouse $w): void;

    public function getById(string $id): Warehouse;

    public function getByCode(string $tenantId, string $code): Warehouse;

    /** @return list<Warehouse> */
    public function listActive(string $tenantId): array;
}

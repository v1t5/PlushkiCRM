<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\Unit;

/** Persistence port for units. */
interface UnitRepo
{
    public function getById(string $id): Unit;

    public function getByCode(string $tenantId, string $code): Unit;

    /** @return list<Unit> */
    public function listActive(string $tenantId): array;
}

<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Ports\UnitRepo;

/** Array-backed UnitRepo for usecase tests. */
final class InMemoryUnitRepo implements UnitRepo
{
    /** @var array<string, Unit> */
    public array $byId = [];

    public function add(Unit $u): void
    {
        $this->byId[$u->id] = $u;
    }

    public function getById(string $id): Unit
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::UnitNotFound);
        }

        return $this->byId[$id];
    }

    public function getByCode(string $tenantId, string $code): Unit
    {
        foreach ($this->byId as $u) {
            if ($u->tenantId === $tenantId && $u->code === $code) {
                return $u;
            }
        }

        throw DomainException::of(ErrorCode::UnitNotFound);
    }

    /** @return list<Unit> */
    public function listActive(string $tenantId): array
    {
        $out = [];
        foreach ($this->byId as $u) {
            if ($u->tenantId === $tenantId && !$u->isArchived()) {
                $out[] = $u;
            }
        }

        return $out;
    }
}

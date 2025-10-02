<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Ports\CategoryRepo;

/** Array-backed CategoryRepo for usecase tests. */
final class InMemoryCategoryRepo implements CategoryRepo
{
    /** @var array<string, Category> */
    public array $byId = [];

    public function add(Category $c): void
    {
        $this->byId[$c->id] = $c;
    }

    public function getById(string $id): Category
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::CategoryNotFound);
        }

        return $this->byId[$id];
    }

    /** @return list<Category> */
    public function listActive(string $tenantId): array
    {
        $out = [];
        foreach ($this->byId as $c) {
            if ($c->tenantId === $tenantId && !$c->isArchived()) {
                $out[] = $c;
            }
        }

        return $out;
    }
}

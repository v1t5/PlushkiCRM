<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\Category;

/** Persistence port for categories. */
interface CategoryRepo
{
    public function getById(string $id): Category;

    /** @return list<Category> */
    public function listActive(string $tenantId): array;
}

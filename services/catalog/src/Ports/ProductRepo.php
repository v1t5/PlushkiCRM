<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\Product;

/** Persistence port for products. */
interface ProductRepo
{
    public function getById(string $id): Product;

    /** @return list<Product> */
    public function listActive(string $tenantId, ?string $categoryId): array;
}

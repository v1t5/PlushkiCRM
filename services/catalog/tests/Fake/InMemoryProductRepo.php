<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Ports\ProductRepo;

/** Array-backed ProductRepo for usecase tests. */
final class InMemoryProductRepo implements ProductRepo
{
    /** @var array<string, Product> */
    public array $byId = [];

    public function add(Product $p): void
    {
        $this->byId[$p->id] = $p;
    }

    public function getById(string $id): Product
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::ProductNotFound);
        }

        return $this->byId[$id];
    }

    /** @return list<Product> */
    public function listActive(string $tenantId, ?string $categoryId): array
    {
        $out = [];
        foreach ($this->byId as $p) {
            if ($p->tenantId !== $tenantId || $p->isArchived()) {
                continue;
            }
            if ($categoryId !== null && $p->categoryId !== $categoryId) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }
}

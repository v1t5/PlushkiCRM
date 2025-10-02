<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Fake;

use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Ports\CatalogClient;
use Plushki\Orders\Ports\CatalogProduct;

/**
 * Array-backed CatalogClient. Products keyed by id; unknown id throws
 * ProductNotFound. Records the ids it was asked to resolve.
 */
final class FakeCatalogClient implements CatalogClient
{
    /** @var array<string, CatalogProduct> */
    private array $products = [];

    /** @var list<string> */
    public array $requested = [];

    public function add(CatalogProduct $p): void
    {
        $this->products[$p->id] = $p;
    }

    public function getProduct(string $id): CatalogProduct
    {
        $this->requested[] = $id;

        return $this->products[$id]
            ?? throw DomainException::of(ErrorCode::ProductNotFound);
    }
}

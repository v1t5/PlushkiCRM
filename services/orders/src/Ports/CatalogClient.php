<?php

declare(strict_types=1);

namespace Plushki\Orders\Ports;

/**
 * CatalogClient calls the catalog service over HTTP. Errors normalise to domain
 * errors (ProductNotFound / CatalogUnavailable) so the app layer stays
 * transport-free.
 */
interface CatalogClient
{
    public function getProduct(string $id): CatalogProduct;
}

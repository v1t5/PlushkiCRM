<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Catalog;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Ports\CatalogClient;
use Plushki\Orders\Ports\CatalogProduct;

/**
 * HttpCatalogClient is the HTTP adapter for the catalog service. Symfony's
 * HttpClient propagates the active OTel span so a placed order shows catalog as
 * a child span in Tempo. Errors normalise to domain errors so the app layer
 * stays transport-free.
 */
final class HttpCatalogClient implements CatalogClient
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $http,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getProduct(string $id): CatalogProduct
    {
        $url = $this->baseUrl . '/v1/products/' . $id;
        try {
            $resp = $this->http->request('GET', $url, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 5.0,
            ]);
            $status = $resp->getStatusCode();
            if ($status === 200) {
                $p = $resp->toArray(false);

                return new CatalogProduct(
                    id: (string) ($p['id'] ?? ''),
                    sku: (string) ($p['sku'] ?? ''),
                    name: (string) ($p['name'] ?? ''),
                    priceKopecks: (int) ($p['price_kopecks'] ?? 0),
                );
            }
            if ($status === 404 || $status === 410) {
                throw DomainException::of(ErrorCode::ProductNotFound);
            }

            throw new DomainException(ErrorCode::CatalogUnavailable, 'catalog status ' . $status);
        } catch (ExceptionInterface $e) {
            // Transport-level failure (DNS, connect, timeout, malformed body).
            throw new DomainException(ErrorCode::CatalogUnavailable, $e->getMessage());
        }
    }
}
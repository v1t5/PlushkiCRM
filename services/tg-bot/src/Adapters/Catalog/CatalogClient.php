<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Catalog;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CatalogClient pulls the product list from the catalog service over HTTP.
 * Symfony's HttpClient propagates the active OTel span so the call shows as a
 * child of the tg.update trace.
 */
final class CatalogClient
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $http,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Pulls active products from catalog. Phase 1 returns all of them —
     * pagination is a Phase 2 concern. Entries with an empty id are skipped.
     *
     * @return list<Product>
     */
    public function listProducts(): array
    {
        try {
            $resp = $this->http->request('GET', $this->baseUrl . '/v1/products', [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 5.0,
            ]);
            if ($resp->getStatusCode() !== 200) {
                throw new \RuntimeException('catalog: status ' . $resp->getStatusCode());
            }
            $data = $resp->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('catalog: ' . $e->getMessage(), 0, $e);
        }

        $out = [];
        foreach ((array) ($data['items'] ?? []) as $p) {
            if (!\is_array($p)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[] = new Product(
                id: $id,
                sku: (string) ($p['sku'] ?? ''),
                name: (string) ($p['name'] ?? ''),
                description: (string) ($p['description'] ?? ''),
                priceKopecks: (int) ($p['price_kopecks'] ?? 0),
            );
        }

        return $out;
    }
}

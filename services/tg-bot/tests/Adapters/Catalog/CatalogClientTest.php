<?php

declare(strict_types=1);

namespace Plushki\TgBot\Tests\Adapters\Catalog;

use Plushki\TgBot\Adapters\Catalog\CatalogClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * CatalogClient::listProducts parsing, driven by an in-memory MockHttpClient.
 * No real network is touched.
 */
final class CatalogClientTest extends TestCase
{
    public function testListProductsMapsItemsAndStripsEntriesWithEmptyId(): void
    {
        $payload = [
            'items' => [
                [
                    'id' => 'p1',
                    'sku' => 'BUN-01',
                    'name' => 'Cinnamon bun',
                    'description' => 'Sweet',
                    'price_kopecks' => 15000,
                ],
                // Empty id -> skipped.
                ['id' => '', 'sku' => 'GHOST'],
                // Non-array entry -> skipped.
                'garbage',
            ],
        ];
        $client = new CatalogClient(
            new MockHttpClient(new MockResponse((string) json_encode($payload), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ])),
            'http://catalog:8080',
        );

        $products = $client->listProducts();

        self::assertCount(1, $products);
        self::assertSame('p1', $products[0]->id);
        self::assertSame('BUN-01', $products[0]->sku);
        self::assertSame('Cinnamon bun', $products[0]->name);
        self::assertSame('Sweet', $products[0]->description);
        self::assertSame(15000, $products[0]->priceKopecks);
    }

    public function testListProductsReturnsEmptyArrayWhenNoItems(): void
    {
        $client = new CatalogClient(
            new MockHttpClient(new MockResponse((string) json_encode(['items' => []]), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ])),
            'http://catalog:8080',
        );

        self::assertSame([], $client->listProducts());
    }

    public function testListProductsThrowsOnNon200(): void
    {
        $client = new CatalogClient(
            new MockHttpClient(new MockResponse('', ['http_code' => 503])),
            'http://catalog:8080',
        );

        $this->expectException(\RuntimeException::class);
        $client->listProducts();
    }

    public function testRequestTargetsTrimmedBaseUrlAndProductsPath(): void
    {
        $seen = null;
        $client = new CatalogClient(
            new MockHttpClient(function (string $method, string $url) use (&$seen): MockResponse {
                $seen = [$method, $url];

                return new MockResponse((string) json_encode(['items' => []]), [
                    'http_code' => 200,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }, 'http://catalog:8080'),
            'http://catalog:8080/', // trailing slash must be trimmed
        );

        $client->listProducts();

        self::assertSame('GET', $seen[0]);
        self::assertSame('http://catalog:8080/v1/products', $seen[1]);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\TgBot\Tests\Adapters\Orders;

use Plushki\TgBot\Adapters\Orders\OrderNotFound;
use Plushki\TgBot\Adapters\Orders\OrdersClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * OrdersClient request shaping and response parsing, via MockHttpClient. The
 * outbound HTTP body for place() is the cross-service contract, so it is asserted
 * field-by-field.
 */
final class OrdersClientTest extends TestCase
{
    private static function jsonResponse(array $data, int $code = 200): MockResponse
    {
        return new MockResponse((string) json_encode($data), [
            'http_code' => $code,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function testPlaceSendsExpectedBodyAndParsesOrder(): void
    {
        $seen = null;
        $client = new OrdersClient(
            new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
                $seen = [$method, $url, $options];

                return self::jsonResponse([
                    'id' => 'o-1',
                    'status' => 'placed',
                    'channel' => 'tg',
                    'customer_ref' => 'tg:555',
                    'total_kopecks' => 15000,
                ], 201);
            }, 'http://orders:8080'),
            'http://orders:8080',
        );

        $order = $client->place('tg:555', 'prod-uuid', 2);

        self::assertSame('POST', $seen[0]);
        self::assertSame('http://orders:8080/v1/orders', $seen[1]);

        $body = json_decode($seen[2]['body'], true);
        self::assertSame('tg', $body['channel']);
        self::assertSame('tg:555', $body['customer_ref']);
        self::assertSame([['product_id' => 'prod-uuid', 'qty' => 2]], $body['items']);

        self::assertSame('o-1', $order->id);
        self::assertSame('placed', $order->status);
        self::assertSame(15000, $order->totalKopecks);
    }

    public function testCancelHitsCancelPathAndParsesOrder(): void
    {
        $seen = null;
        $client = new OrdersClient(
            new MockHttpClient(function (string $method, string $url) use (&$seen): MockResponse {
                $seen = [$method, $url];

                return self::jsonResponse(['id' => 'o-9', 'status' => 'cancelled']);
            }, 'http://orders:8080'),
            'http://orders:8080',
        );

        $order = $client->cancel('o-9');

        self::assertSame('POST', $seen[0]);
        self::assertSame('http://orders:8080/v1/orders/o-9/cancel', $seen[1]);
        self::assertSame('cancelled', $order->status);
    }

    public function testMutate404MapsToOrderNotFound(): void
    {
        $client = new OrdersClient(
            new MockHttpClient(self::jsonResponse([], 404)),
            'http://orders:8080',
        );

        $this->expectException(OrderNotFound::class);
        $client->cancel('missing');
    }

    public function testMutateOtherErrorThrowsRuntimeException(): void
    {
        $client = new OrdersClient(
            new MockHttpClient(self::jsonResponse([], 500)),
            'http://orders:8080',
        );

        $this->expectException(\RuntimeException::class);
        $client->confirm('boom');
    }

    public function testListByCustomerSendsQueryAndParsesItems(): void
    {
        $seen = null;
        $client = new OrdersClient(
            new MockHttpClient(function (string $method, string $url) use (&$seen): MockResponse {
                $seen = [$method, $url];

                return self::jsonResponse([
                    'items' => [
                        ['id' => 'a', 'status' => 'placed', 'total_kopecks' => 100],
                        ['id' => 'b', 'status' => 'done', 'total_kopecks' => 200],
                        'garbage',
                    ],
                ]);
            }, 'http://orders:8080'),
            'http://orders:8080',
        );

        $orders = $client->listByCustomer('tg:555', 5);

        self::assertSame('GET', $seen[0]);
        self::assertStringStartsWith('http://orders:8080/v1/orders', $seen[1]);
        self::assertStringContainsString('customer_ref=tg', $seen[1]);
        self::assertStringContainsString('limit=5', $seen[1]);

        self::assertCount(2, $orders);
        self::assertSame('a', $orders[0]->id);
        self::assertSame('done', $orders[1]->status);
    }

    public function testListByCustomerOmitsLimitWhenNotPositive(): void
    {
        $seen = null;
        $client = new OrdersClient(
            new MockHttpClient(function (string $method, string $url) use (&$seen): MockResponse {
                $seen = $url;

                return self::jsonResponse(['items' => []]);
            }, 'http://orders:8080'),
            'http://orders:8080',
        );

        $client->listByCustomer('tg:1', 0);

        self::assertStringNotContainsString('limit=', $seen);
    }
}

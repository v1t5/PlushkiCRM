<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Orders;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OrdersClient talks to the orders service over HTTP. place/cancel/confirm map
 * orders 404 to OrderNotFound; listByCustomer uses the by-customer mode of
 * GET /v1/orders.
 */
final class OrdersClient
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $http,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function place(string $customerRef, string $productId, int $qty): Order
    {
        return $this->mutate('POST', '/v1/orders', [
            'channel' => 'tg',
            'customer_ref' => $customerRef,
            'items' => [['product_id' => $productId, 'qty' => $qty]],
        ]);
    }

    public function cancel(string $id): Order
    {
        return $this->mutate('POST', sprintf('/v1/orders/%s/cancel', $id), null);
    }

    public function confirm(string $id): Order
    {
        return $this->mutate('POST', sprintf('/v1/orders/%s/confirm', $id), null);
    }

    /**
     * @return list<Order>
     */
    public function listByCustomer(string $customerRef, int $limit): array
    {
        $query = ['customer_ref' => $customerRef];
        if ($limit > 0) {
            $query['limit'] = (string) $limit;
        }
        try {
            $resp = $this->http->request('GET', $this->baseUrl . '/v1/orders', [
                'query' => $query,
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10.0,
            ]);
            if ($resp->getStatusCode() !== 200) {
                throw new \RuntimeException('orders: status ' . $resp->getStatusCode());
            }
            $data = $resp->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('orders: ' . $e->getMessage(), 0, $e);
        }

        $out = [];
        foreach ((array) ($data['items'] ?? []) as $o) {
            if (\is_array($o)) {
                $out[] = Order::fromArray($o);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function mutate(string $method, string $path, ?array $body): Order
    {
        try {
            $options = ['headers' => ['Accept' => 'application/json'], 'timeout' => 10.0];
            if ($body !== null) {
                $options['json'] = $body;
            }
            $resp = $this->http->request($method, $this->baseUrl . $path, $options);
            $status = $resp->getStatusCode();
            if ($status === 200 || $status === 201) {
                return Order::fromArray($resp->toArray(false));
            }
            if ($status === 404) {
                throw new OrderNotFound('not found');
            }
            throw new \RuntimeException(sprintf('orders: status %d', $status));
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('orders: ' . $e->getMessage(), 0, $e);
        }
    }
}

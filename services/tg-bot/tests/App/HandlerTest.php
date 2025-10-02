<?php

declare(strict_types=1);

namespace Plushki\TgBot\Tests\App;

use Plushki\TgBot\Adapters\Catalog\CatalogClient;
use Plushki\TgBot\Adapters\Orders\OrdersClient;
use Plushki\TgBot\Adapters\Telegram\Api;
use Plushki\TgBot\Adapters\Telegram\Update;
use Plushki\TgBot\App\Handler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * End-to-end Handler flow with no network. The concrete Api/Catalog/Orders
 * clients are real but wired to a single MockHttpClient that routes by URL and
 * records every outbound request, so we can assert routing, the orders "place"
 * call, and the rendered Telegram messages.
 *
 * Handler's clients are final classes, so they cannot be subclassed; instead we
 * inject the seam they all expose — the HttpClientInterface.
 */
final class HandlerTest extends TestCase
{
    /** A real UUID v7-ish value Symfony's Uuid::isValid accepts. */
    private const PRODUCT_ID = '0190d1a0-0000-7000-8000-000000000001';

    /** @var list<array{method:string,url:string,body:?array}> */
    private array $requests = [];

    /**
     * @param array<string,mixed> $catalogItems
     * @param array<string,mixed> $orderRow   parsed back for place/cancel responses
     * @param list<array<string,mixed>> $customerOrders
     */
    private function makeHandler(
        array $catalogItems = [],
        array $orderRow = [],
        array $customerOrders = [],
    ): Handler {
        $this->requests = [];

        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = null;
            if (isset($options['body']) && \is_string($options['body'])) {
                $decoded = json_decode($options['body'], true);
                $body = \is_array($decoded) ? $decoded : null;
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $body];

            return $this->respond($url);
        });

        // Captured state for respond().
        $this->catalogItems = $catalogItems;
        $this->orderRow = $orderRow;
        $this->customerOrders = $customerOrders;

        $api = new Api($http, 'https://api.telegram.org', 'TOKEN');
        $catalog = new CatalogClient($http, 'http://catalog:8080');
        $orders = new OrdersClient($http, 'http://orders:8080');

        return new Handler($api, $catalog, $orders, new NullLogger());
    }

    /** @var list<array<string,mixed>> */
    private array $catalogItems = [];
    /** @var array<string,mixed> */
    private array $orderRow = [];
    /** @var list<array<string,mixed>> */
    private array $customerOrders = [];

    private function respond(string $url): MockResponse
    {
        $json = static fn (array $d, int $code = 200): MockResponse => new MockResponse(
            (string) json_encode($d),
            ['http_code' => $code, 'response_headers' => ['content-type' => 'application/json']],
        );

        if (str_contains($url, '/v1/products')) {
            return $json(['items' => $this->catalogItems]);
        }
        if (str_contains($url, '/v1/orders') && (str_contains($url, '/cancel') || str_contains($url, '/confirm'))) {
            return $json($this->orderRow, 200);
        }
        // place: POST /v1/orders ; list: GET /v1/orders?...
        if (str_contains($url, '/v1/orders')) {
            // A GET with a query string is the list call.
            if (str_contains($url, '?')) {
                return $json(['items' => $this->customerOrders]);
            }

            return $json($this->orderRow, 201);
        }
        // Telegram sendMessage / answerCallbackQuery.
        return $json(['ok' => true]);
    }

    /** @return list<array{method:string,url:string,body:?array}> */
    private function telegramCalls(string $method): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (array $r): bool => str_contains($r['url'], 'api.telegram.org')
                && str_contains($r['url'], '/' . $method),
        ));
    }

    private static function messageUpdate(string $text, int $chatId = 555): Update
    {
        return Update::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 1, 'text' => $text, 'chat' => ['id' => $chatId]],
        ]);
    }

    public function testHelpCommandSendsUsageText(): void
    {
        $h = $this->makeHandler();
        $h->handleUpdate(self::messageUpdate('/help'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertSame(555, $sent[0]['body']['chat_id']);
        self::assertStringContainsString('/menu', $sent[0]['body']['text']);
        self::assertStringContainsString('/status', $sent[0]['body']['text']);
    }

    public function testStartIsAnAliasForHelp(): void
    {
        $h = $this->makeHandler();
        $h->handleUpdate(self::messageUpdate('/start'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('Available commands', $sent[0]['body']['text']);
    }

    public function testUnknownCommandSendsFallback(): void
    {
        $h = $this->makeHandler();
        $h->handleUpdate(self::messageUpdate('/wat'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('Unknown command', $sent[0]['body']['text']);
    }

    public function testCommandWithBotnameSuffixIsStripped(): void
    {
        $h = $this->makeHandler();
        $h->handleUpdate(self::messageUpdate('/help@PlushkiBot'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('Available commands', $sent[0]['body']['text']);
    }

    public function testMenuRendersProductsAsInlineButtons(): void
    {
        $h = $this->makeHandler(catalogItems: [
            ['id' => self::PRODUCT_ID, 'sku' => 'BUN-01', 'name' => 'Bun', 'price_kopecks' => 15000],
        ]);
        $h->handleUpdate(self::messageUpdate('/menu'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        $body = $sent[0]['body'];
        self::assertStringContainsString('Bun', $body['text']);
        self::assertStringContainsString('150.00', $body['text']); // 15000 kopecks -> rubles

        $btn = $body['reply_markup']['inline_keyboard'][0][0];
        self::assertSame('place:' . self::PRODUCT_ID, $btn['callback_data']);
        self::assertStringContainsString('BUN-01', $btn['text']);
    }

    public function testMenuEmptyCatalogSendsPlaceholder(): void
    {
        $h = $this->makeHandler(catalogItems: []);
        $h->handleUpdate(self::messageUpdate('/menu'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('empty', $sent[0]['body']['text']);
        self::assertArrayNotHasKey('reply_markup', $sent[0]['body']);
    }

    public function testOrderIsAnAliasForMenu(): void
    {
        $h = $this->makeHandler(catalogItems: [
            ['id' => self::PRODUCT_ID, 'sku' => 'X', 'name' => 'Y', 'price_kopecks' => 100],
        ]);
        $h->handleUpdate(self::messageUpdate('/order'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertArrayHasKey('reply_markup', $sent[0]['body']);
    }

    public function testPlaceCallbackBuildsOrdersCallWithChatCustomerRef(): void
    {
        $h = $this->makeHandler(orderRow: [
            'id' => '0190d1a0-0000-7000-8000-0000000000aa',
            'status' => 'placed',
            'total_kopecks' => 15000,
        ]);

        $u = Update::fromArray([
            'update_id' => 2,
            'callback_query' => [
                'id' => 'cb-1',
                'data' => 'place:' . self::PRODUCT_ID,
                'message' => ['message_id' => 9, 'chat' => ['id' => 777]],
            ],
        ]);
        $h->handleUpdate($u);

        // The orders POST body is the cross-service contract.
        $orderPosts = array_values(array_filter(
            $this->requests,
            static fn (array $r): bool => str_contains($r['url'], 'orders:8080/v1/orders')
                && $r['method'] === 'POST'
                && !str_contains($r['url'], '/cancel'),
        ));
        self::assertCount(1, $orderPosts);
        $body = $orderPosts[0]['body'];
        self::assertSame('tg', $body['channel']);
        self::assertSame('tg:777', $body['customer_ref']);
        self::assertSame(self::PRODUCT_ID, $body['items'][0]['product_id']);
        self::assertSame(1, $body['items'][0]['qty']);

        // Confirmation message references the placed order + ruble total.
        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('Placed', $sent[0]['body']['text']);
        self::assertStringContainsString('150.00', $sent[0]['body']['text']);
    }

    public function testPlaceCallbackWithInvalidUuidDoesNotCallOrders(): void
    {
        $h = $this->makeHandler();

        $u = Update::fromArray([
            'update_id' => 3,
            'callback_query' => [
                'id' => 'cb-2',
                'data' => 'place:not-a-uuid',
                'message' => ['message_id' => 1, 'chat' => ['id' => 1]],
            ],
        ]);
        $h->handleUpdate($u);

        $orderPosts = array_filter(
            $this->requests,
            static fn (array $r): bool => str_contains($r['url'], 'orders:8080'),
        );
        self::assertCount(0, $orderPosts);

        // It still answers the callback (with "Bad item.").
        $answers = $this->telegramCalls('answerCallbackQuery');
        self::assertCount(1, $answers);
        self::assertSame('Bad item.', $answers[0]['body']['text']);
    }

    public function testStatusListsOrdersAndAddsCancelButtonsForCancellable(): void
    {
        $cancellableId = '0190d1a0-0000-7000-8000-0000000000bb';
        $doneId = '0190d1a0-0000-7000-8000-0000000000cc';
        $h = $this->makeHandler(customerOrders: [
            ['id' => $cancellableId, 'status' => 'placed', 'total_kopecks' => 500],
            ['id' => $doneId, 'status' => 'delivered', 'total_kopecks' => 700],
        ]);

        $h->handleUpdate(self::messageUpdate('/status', 888));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        $body = $sent[0]['body'];
        self::assertStringContainsString('placed', $body['text']);
        self::assertStringContainsString('delivered', $body['text']);

        // Only the cancellable (placed) order gets a cancel button.
        $rows = $body['reply_markup']['inline_keyboard'];
        self::assertCount(1, $rows);
        self::assertSame('cancel:' . $cancellableId, $rows[0][0]['callback_data']);
    }

    public function testStatusWithNoOrdersSendsPlaceholder(): void
    {
        $h = $this->makeHandler(customerOrders: []);
        $h->handleUpdate(self::messageUpdate('/status'));

        $sent = $this->telegramCalls('sendMessage');
        self::assertCount(1, $sent);
        self::assertStringContainsString('No orders yet', $sent[0]['body']['text']);
    }

    public function testCallbackWithMalformedDataJustAnswers(): void
    {
        $h = $this->makeHandler();

        $u = Update::fromArray([
            'update_id' => 4,
            'callback_query' => [
                'id' => 'cb-3',
                'data' => 'no-colon-here',
                'message' => ['message_id' => 1, 'chat' => ['id' => 1]],
            ],
        ]);
        $h->handleUpdate($u);

        self::assertCount(1, $this->telegramCalls('answerCallbackQuery'));
        self::assertCount(0, $this->telegramCalls('sendMessage'));
    }

    public function testEmptyTextMessageIsIgnored(): void
    {
        $h = $this->makeHandler();
        $h->handleUpdate(Update::fromArray([
            'update_id' => 5,
            'message' => ['message_id' => 1, 'text' => '', 'chat' => ['id' => 1]],
        ]));

        self::assertCount(0, $this->requests);
    }
}

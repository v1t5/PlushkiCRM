<?php

declare(strict_types=1);

namespace Plushki\TgBot\App;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\TgBot\Adapters\Catalog\CatalogClient;
use Plushki\TgBot\Adapters\Orders\OrderNotFound;
use Plushki\TgBot\Adapters\Orders\OrdersClient;
use Plushki\TgBot\Adapters\Telegram\Api;
use Plushki\TgBot\Adapters\Telegram\CallbackQuery;
use Plushki\TgBot\Adapters\Telegram\Message;
use Plushki\TgBot\Adapters\Telegram\Update;

/**
 * Handler is the bot's only use-case orchestrator. Phase 1 commands:
 *   /start, /help — usage text.
 *   /menu, /order — list products as inline buttons; tap places a single-item order.
 *   /status       — list the chat's last 5 orders with inline cancel buttons.
 *
 * One tap = one order keeps the bot stateless; multi-item carts are deferred.
 */
final class Handler
{
    public function __construct(
        private readonly Api $api,
        private readonly CatalogClient $catalog,
        private readonly OrdersClient $orders,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleUpdate(Update $u): void
    {
        if ($u->message !== null && $u->message->text !== '') {
            $this->handleMessage($u->message);
        } elseif ($u->callbackQuery !== null) {
            $this->handleCallback($u->callbackQuery);
        }
    }

    private function handleMessage(Message $m): void
    {
        $text = trim($m->text);
        $cmd = strtolower(explode(' ', $text, 2)[0]);
        // Strip the @botname suffix Telegram appends in group chats.
        if (($i = strpos($cmd, '@')) !== false && $i > 0) {
            $cmd = substr($cmd, 0, $i);
        }
        switch ($cmd) {
            case '/start':
            case '/help':
                $this->replyHelp($m->chat->id);
                break;
            case '/menu':
            case '/order':
                $this->replyMenu($m->chat->id);
                break;
            case '/status':
                $this->replyStatus($m->chat->id);
                break;
            default:
                $this->send($m->chat->id, 'Unknown command. Try /menu or /help.');
        }
    }

    private function handleCallback(CallbackQuery $q): void
    {
        if ($q->message === null) {
            $this->answer($q->id);

            return;
        }
        $chatId = $q->message->chat->id;
        $parts = explode(':', $q->data, 2);
        if (\count($parts) !== 2) {
            $this->answer($q->id);

            return;
        }
        [$prefix, $payload] = $parts;

        switch ($prefix) {
            case 'place':
                if (!Uuid::isValid($payload)) {
                    $this->answer($q->id, 'Bad item.');

                    return;
                }
                $this->answer($q->id, 'Placing…');
                try {
                    $o = $this->orders->place($this->customerRef($chatId), $payload, 1);
                } catch (\Throwable $e) {
                    $this->logger->warning('place failed', ['err' => $e->getMessage()]);
                    $this->send($chatId, "Couldn't place that order. Try /menu again in a moment.");

                    return;
                }
                $this->send($chatId, sprintf(
                    "Placed #%s — %s.\nYou'll get a confirmation shortly.",
                    self::shortID($o->id),
                    self::formatRubles($o->totalKopecks),
                ));
                break;
            case 'cancel':
                if (!Uuid::isValid($payload)) {
                    $this->answer($q->id, 'Bad order.');

                    return;
                }
                $this->answer($q->id, 'Cancelling…');
                try {
                    $o = $this->orders->cancel($payload);
                } catch (OrderNotFound) {
                    $this->send($chatId, 'Order not found.');

                    return;
                } catch (\Throwable $e) {
                    $this->logger->warning('cancel failed', ['err' => $e->getMessage()]);
                    $this->send($chatId, "Couldn't cancel — it may already be in a state that can't be cancelled.");

                    return;
                }
                $this->send($chatId, sprintf('Cancelled order #%s.', self::shortID($o->id)));
                break;
            default:
                $this->answer($q->id);
        }
    }

    private function replyHelp(int $chatId): void
    {
        $body = implode("\n", [
            'Available commands:',
            '/menu — show products and order with one tap',
            '/status — show your last 5 orders',
            '/help — show this message',
        ]);
        $this->send($chatId, $body);
    }

    private function replyMenu(int $chatId): void
    {
        try {
            $products = $this->catalog->listProducts();
        } catch (\Throwable $e) {
            $this->logger->warning('list products', ['err' => $e->getMessage()]);
            $this->send($chatId, "Couldn't load the menu. Try again in a moment.");

            return;
        }
        if ($products === []) {
            $this->send($chatId, 'Menu is empty for now.');

            return;
        }
        $rows = [];
        $body = "Pick something — one tap = one order placed.\n";
        foreach ($products as $i => $p) {
            $body .= sprintf("\n%d. %s — %s", $i + 1, $p->name, self::formatRubles($p->priceKopecks));
            $rows[] = [[
                'text' => sprintf('Order %s — %s', $p->sku, self::formatRubles($p->priceKopecks)),
                'callback_data' => 'place:' . $p->id,
            ]];
        }
        $this->send($chatId, $body, ['inline_keyboard' => $rows]);
    }

    private function replyStatus(int $chatId): void
    {
        try {
            $got = $this->orders->listByCustomer($this->customerRef($chatId), 5);
        } catch (\Throwable $e) {
            $this->logger->warning('list orders', ['err' => $e->getMessage()]);
            $this->send($chatId, "Couldn't load your orders. Try again in a moment.");

            return;
        }
        if ($got === []) {
            $this->send($chatId, 'No orders yet. Use /menu to place one.');

            return;
        }
        $body = '';
        $rows = [];
        foreach ($got as $o) {
            $body .= sprintf("#%s — %s — %s\n", self::shortID($o->id), $o->status, self::formatRubles($o->totalKopecks));
            if ($o->status === 'placed' || $o->status === 'confirmed') {
                $rows[] = [[
                    'text' => sprintf('Cancel #%s', self::shortID($o->id)),
                    'callback_data' => 'cancel:' . $o->id,
                ]];
            }
        }
        $markup = $rows !== [] ? ['inline_keyboard' => $rows] : null;
        $this->send($chatId, rtrim($body, "\n"), $markup);
    }

    /**
     * @param array<string, mixed>|null $markup
     */
    private function send(int $chatId, string $text, ?array $markup = null): void
    {
        try {
            $this->api->sendMessage($chatId, $text, $markup);
        } catch (\Throwable $e) {
            $this->logger->warning('send', ['chat_id' => $chatId, 'err' => $e->getMessage()]);
        }
    }

    private function answer(string $callbackId, string $text = ''): void
    {
        try {
            $this->api->answerCallbackQuery($callbackId, $text);
        } catch (\Throwable $e) {
            $this->logger->warning('answer callback', ['err' => $e->getMessage()]);
        }
    }

    /**
     * customerRef encodes the Telegram chat id as the orders-service customer
     * reference. Notifications splits this back to <channel>:<id>, so the prefix
     * must stay "tg".
     */
    private function customerRef(int $chatId): string
    {
        return 'tg:' . $chatId;
    }

    private static function shortID(string $id): string
    {
        return \strlen($id) < 8 ? $id : substr($id, -8);
    }

    private static function formatRubles(int $kopecks): string
    {
        return sprintf('%d.%02d ₽', intdiv($kopecks, 100), $kopecks % 100);
    }
}

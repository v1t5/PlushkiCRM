<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Api is a thin HTTP client over the Telegram Bot API. Only the handful of
 * methods tg-bot calls (getUpdates / sendMessage / answerCallbackQuery) are
 * wrapped. Symfony's HttpClient propagates the active OTel span so outbound
 * calls stitch into the `tg.update` trace.
 */
final class Api
{
    private readonly string $apiBase;

    public function __construct(
        private readonly HttpClientInterface $http,
        string $apiBase,
        private readonly string $token,
    ) {
        $this->apiBase = rtrim($apiBase, '/');
    }

    /**
     * Long-polls for new updates. timeoutSec >= 1 enables long-poll; 0 returns
     * whatever is queued.
     *
     * @return list<Update>
     */
    public function getUpdates(int $offset, int $timeoutSec): array
    {
        $parsed = $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeoutSec,
            'allowed_updates' => ['message', 'callback_query'],
        ], readTimeout: $timeoutSec + 10.0);

        if (($parsed['ok'] ?? false) !== true) {
            throw new \RuntimeException('getUpdates: ' . (string) ($parsed['description'] ?? ''));
        }
        $out = [];
        foreach ((array) ($parsed['result'] ?? []) as $u) {
            if (\is_array($u)) {
                $out[] = Update::fromArray($u);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $replyMarkup an inline_keyboard markup array, or null
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $body = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup !== null) {
            $body['reply_markup'] = $replyMarkup;
        }
        $parsed = $this->call('sendMessage', $body);
        if (($parsed['ok'] ?? false) !== true) {
            throw new \RuntimeException('sendMessage: ' . (string) ($parsed['description'] ?? ''));
        }
    }

    /**
     * Acknowledges a tapped inline button. Telegram keeps the button "loading"
     * until this fires, so send it before any heavy work.
     */
    public function answerCallbackQuery(string $callbackId, string $text = ''): void
    {
        $body = ['callback_query_id' => $callbackId];
        if ($text !== '') {
            $body['text'] = $text;
        }
        $parsed = $this->call('answerCallbackQuery', $body);
        if (($parsed['ok'] ?? false) !== true) {
            throw new \RuntimeException('answerCallbackQuery: ' . (string) ($parsed['description'] ?? ''));
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function call(string $method, array $body, float $readTimeout = 60.0): array
    {
        $url = sprintf('%s/bot%s/%s', $this->apiBase, $this->token, $method);
        try {
            $resp = $this->http->request('POST', $url, [
                'json' => $body,
                'timeout' => $readTimeout,
            ]);
            $status = $resp->getStatusCode();
            if ($status >= 500) {
                throw new \RuntimeException(sprintf('tg %s: status %d', $method, $status));
            }

            return $resp->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException(sprintf('tg %s: %s', $method, $e->getMessage()), 0, $e);
        }
    }
}

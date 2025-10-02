<?php

declare(strict_types=1);

namespace Plushki\Notifications\Adapters\Telegram;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\DomainException;
use Plushki\Notifications\Domain\ErrorCode;
use Plushki\Notifications\Domain\Notification;
use Plushki\Notifications\Ports\Sender as SenderPort;

/**
 * Talks to the Telegram Bot HTTP API. With an empty bot token it runs in dry-run
 * mode: it logs the would-be request and reports success, so notifications is
 * wireable in dev before tg-bot is ready. Symfony's HttpClient propagates the
 * active OTel span so the send shows as a child in Tempo.
 *
 * Error mapping:
 *   - 200 + ok:true                      -> success,
 *   - 400/401/403                        -> non-retryable (bad token/chat) —
 *     a plain DomainException (no SendFailed) so the handler still naks but the
 *     log says "send error" rather than "will retry",
 *   - transport error / other status     -> SendFailed (retryable).
 */
final class Sender implements SenderPort
{
    private readonly string $apiBase;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        string $apiBase,
        private readonly string $token,
    ) {
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function channel(): Channel
    {
        return Channel::TG;
    }

    public function send(Notification $n): void
    {
        if ($this->token === '') {
            $this->logger->info('tg dry-run send', [
                'chat_id' => $n->recipient->id,
                'schema' => $n->schema,
                'body' => $n->body,
            ]);

            return;
        }

        $url = sprintf('%s/bot%s/sendMessage', $this->apiBase, $this->token);
        try {
            $resp = $this->http->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['chat_id' => $n->recipient->id, 'text' => $n->body],
                'timeout' => 10.0,
            ]);
            $status = $resp->getStatusCode();
            $parsed = $resp->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new DomainException(ErrorCode::SendFailed, $e->getMessage());
        }

        $ok = ($parsed['ok'] ?? false) === true;
        $description = (string) ($parsed['description'] ?? '');

        if ($status === 200 && $ok) {
            return;
        }
        if (\in_array($status, [400, 401, 403], true)) {
            // Non-retryable: blocked bot, invalid chat, bad token.
            throw new DomainException(ErrorCode::InvalidRecipient, sprintf('telegram %d: %s', $status, $description));
        }

        throw new DomainException(ErrorCode::SendFailed, sprintf('status %d: %s', $status, $description));
    }
}

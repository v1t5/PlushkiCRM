<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

/**
 * A single Telegram update (subset of the upstream schema). Only the fields
 * tg-bot consumes are decoded.
 */
final class Update
{
    public function __construct(
        public readonly int $updateId,
        public readonly ?Message $message,
        public readonly ?CallbackQuery $callbackQuery,
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            updateId: (int) ($a['update_id'] ?? 0),
            message: \is_array($a['message'] ?? null) ? Message::fromArray($a['message']) : null,
            callbackQuery: \is_array($a['callback_query'] ?? null) ? CallbackQuery::fromArray($a['callback_query']) : null,
        );
    }
}

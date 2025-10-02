<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

/** The subset of a Telegram message the bot consumes. */
final class Message
{
    public function __construct(
        public readonly int $messageId,
        public readonly Chat $chat,
        public readonly string $text = '',
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            messageId: (int) ($a['message_id'] ?? 0),
            chat: Chat::fromArray(\is_array($a['chat'] ?? null) ? $a['chat'] : []),
            text: (string) ($a['text'] ?? ''),
        );
    }
}

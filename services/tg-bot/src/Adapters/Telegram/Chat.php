<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

/**
 * The subset of a Telegram chat the bot consumes. Only the id is used for
 * routing; type/username are decoded but unused in Phase 1.
 */
final class Chat
{
    public function __construct(
        public readonly int $id,
        public readonly string $type = '',
        public readonly string $username = '',
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            id: (int) ($a['id'] ?? 0),
            type: (string) ($a['type'] ?? ''),
            username: (string) ($a['username'] ?? ''),
        );
    }
}

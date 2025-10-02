<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

/**
 * The inline-button payload. Data is the bot-defined string set when the button
 * was rendered ("place:<uuid>" / "cancel:<uuid>" — see App\Handler).
 */
final class CallbackQuery
{
    public function __construct(
        public readonly string $id,
        public readonly ?Message $message,
        public readonly string $data = '',
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            id: (string) ($a['id'] ?? ''),
            message: \is_array($a['message'] ?? null) ? Message::fromArray($a['message']) : null,
            data: (string) ($a['data'] ?? ''),
        );
    }
}

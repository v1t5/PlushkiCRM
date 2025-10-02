<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * A rendered message ready for a Sender. The body is pre-rendered text; the
 * Telegram sender stays dumb and doesn't know about templates.
 */
final class Notification
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $schema,
        public readonly string $subject,
        public readonly Recipient $recipient,
        public readonly string $body,
    ) {
    }
}

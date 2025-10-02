<?php

declare(strict_types=1);

namespace Plushki\Notifications\Ports;

use Plushki\Notifications\Domain\Recipient;

/**
 * The consumer-side dedup store. Reservation is racy by design: tryReserve
 * attempts an INSERT; a conflict means another delivery of the same event_id
 * beat us to it and we should ack-and-skip.
 */
interface DeliveryRepo
{
    /**
     * Attempt to claim $eventId for processing. Returns true on a fresh insert;
     * false on a primary-key conflict (already processed).
     */
    public function tryReserve(
        string $eventId,
        string $schema,
        string $subject,
        Recipient $recipient,
        \DateTimeImmutable $at,
    ): bool;

    /**
     * Roll back a reservation when the send fails and the next redelivery must
     * actually run. Idempotent: a missing row is not an error.
     */
    public function delete(string $eventId): void;
}

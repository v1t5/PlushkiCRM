<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Fake;

use Plushki\Notifications\Domain\Recipient;
use Plushki\Notifications\Ports\DeliveryRepo;

/**
 * Array-backed in-memory DeliveryRepo. tryReserve mimics an INSERT with a
 * primary-key conflict on event_id: the first reserve of an id succeeds, a
 * repeat returns false until delete() rolls it back.
 */
final class FakeDeliveryRepo implements DeliveryRepo
{
    /** @var array<string, array{schema: string, subject: string, recipient: Recipient, at: \DateTimeImmutable}> */
    public array $rows = [];

    /** @var list<string> event ids passed to tryReserve, in order */
    public array $reserveCalls = [];

    /** @var list<string> event ids passed to delete, in order */
    public array $deleteCalls = [];

    /** When set, tryReserve throws this instead of reserving. */
    public ?\Throwable $reserveThrows = null;

    /** When set, delete throws this. */
    public ?\Throwable $deleteThrows = null;

    public function tryReserve(
        string $eventId,
        string $schema,
        string $subject,
        Recipient $recipient,
        \DateTimeImmutable $at,
    ): bool {
        $this->reserveCalls[] = $eventId;
        if ($this->reserveThrows !== null) {
            throw $this->reserveThrows;
        }
        if (isset($this->rows[$eventId])) {
            return false;
        }
        $this->rows[$eventId] = [
            'schema' => $schema,
            'subject' => $subject,
            'recipient' => $recipient,
            'at' => $at,
        ];

        return true;
    }

    public function delete(string $eventId): void
    {
        $this->deleteCalls[] = $eventId;
        if ($this->deleteThrows !== null) {
            throw $this->deleteThrows;
        }
        unset($this->rows[$eventId]);
    }
}

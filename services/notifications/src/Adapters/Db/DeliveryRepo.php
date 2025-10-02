<?php

declare(strict_types=1);

namespace Plushki\Notifications\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Notifications\Domain\Recipient;
use Plushki\Notifications\Ports\DeliveryRepo as DeliveryRepoPort;

/**
 * DBAL-backed dedup store. tryReserve attempts to claim event_id with an INSERT;
 * a unique-violation means the event was already handled and we ack-and-skip.
 * Returning the conflict as a flag (not an exception) keeps the app layer free
 * of DB-specific imports.
 */
final class DeliveryRepo implements DeliveryRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function tryReserve(
        string $eventId,
        string $schema,
        string $subject,
        Recipient $recipient,
        \DateTimeImmutable $at,
    ): bool {
        try {
            $this->db->executeStatement(
                'INSERT INTO delivered_notifications
                    (event_id, schema, subject, channel, recipient, delivered_at, attempt)
                 VALUES (CAST(:event_id AS uuid), :schema, :subject, :channel, :recipient,
                         CAST(:at AS timestamptz), 1)',
                [
                    'event_id' => $eventId,
                    'schema' => $schema,
                    'subject' => $subject,
                    'channel' => $recipient->channel->value,
                    'recipient' => $recipient->id,
                    'at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function delete(string $eventId): void
    {
        // Idempotent: a missing row affects 0 rows and is not an error.
        $this->db->executeStatement(
            'DELETE FROM delivered_notifications WHERE event_id = CAST(:event_id AS uuid)',
            ['event_id' => $eventId],
        );
    }
}

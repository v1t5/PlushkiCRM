<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Ports\MovementPostedIn;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * Projects inventory.v1.movement_posted.# into movements_by_day. qty_in_base is
 * kept signed.
 */
final class MovementsConsumer
{
    public function __construct(
        private readonly ProjectionRepo $repo,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        if (!Uuid::isValid($env->eventId)) {
            $this->logger->warning('event id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid event_id');
        }
        $itemId = (string) ($d['item_id'] ?? '');
        if (!Uuid::isValid($itemId)) {
            throw new PoisonException('invalid item_id');
        }
        // movement_posted carries its own data.occurred_at; fall back to envelope.
        $occurredAt = self::parseTime((string) ($d['occurred_at'] ?? '') ?: $env->occurredAt);

        $this->repo->applyMovementPosted(new MovementPostedIn(
            eventId: $env->eventId,
            tenantId: $env->tenantId !== '' ? $env->tenantId : 'default',
            day: $occurredAt,
            itemKind: (string) ($d['item_kind'] ?? ''),
            itemId: $itemId,
            itemSku: (string) ($d['item_sku'] ?? ''),
            itemName: (string) ($d['item_name'] ?? ''),
            type: (string) ($d['type'] ?? ''),
            qtyInBase: (int) ($d['qty_in_base'] ?? 0),
            occurredAt: $occurredAt,
        ));
    }

    private static function parseTime(string $s): \DateTimeImmutable
    {
        if ($s !== '') {
            try {
                return new \DateTimeImmutable($s);
            } catch (\Throwable) {
            }
        }

        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

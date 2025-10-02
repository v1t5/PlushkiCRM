<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Ports\FulfilledIn;
use Plushki\Reporting\Ports\FulfilledItem;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * Projects orders.v1.fulfilled.# into sales_by_day + top_items. Outcome:
 * return=ack, PoisonException=drop, throw=requeue. Idempotency lives in the
 * repo (applied_events).
 */
final class OrdersConsumer
{
    public function __construct(
        private readonly ProjectionRepo $repo,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        if ((string) ($d['status'] ?? '') !== 'fulfilled') {
            return;
        }
        if (!Uuid::isValid($env->eventId)) {
            $this->logger->warning('event id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid event_id');
        }
        $occurredAt = self::parseTime($env->occurredAt);

        $items = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $pid = (string) ($it['product_id'] ?? '');
            if (!Uuid::isValid($pid)) {
                throw new PoisonException('invalid product_id');
            }
            $items[] = new FulfilledItem(
                productId: $pid,
                sku: (string) ($it['sku'] ?? ''),
                name: (string) ($it['name'] ?? ''),
                qty: (int) ($it['qty'] ?? 0),
                priceKopecks: (int) ($it['price_kopecks'] ?? 0),
            );
        }

        $this->repo->applyFulfilled(new FulfilledIn(
            eventId: $env->eventId,
            tenantId: $env->tenantId !== '' ? $env->tenantId : 'default',
            day: $occurredAt,
            channel: (string) ($d['channel'] ?? ''),
            totalKopecks: (int) ($d['total_kopecks'] ?? 0),
            occurredAt: $occurredAt,
            items: $items,
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

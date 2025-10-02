<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Crm\App\FulfilledInput;
use Plushki\Crm\App\LoyaltyService;
use Plushki\Crm\Platform\Events\Envelope;
use Plushki\Crm\Platform\Events\PoisonException;

/**
 * OrdersConsumer bumps loyalty from orders.v1.fulfilled.#. One bump per
 * fulfilled order; idempotent at the DB layer (applied_order_events). Outcome:
 * return=ack, PoisonException=drop, throw=requeue. An unattributable
 * customer_ref is skipped inside the service (still acks).
 */
final class OrdersConsumer
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        if ((string) ($d['status'] ?? '') !== 'fulfilled') {
            return; // ack — not a fulfilment
        }
        if (!Uuid::isValid($env->eventId)) {
            $this->logger->warning('event id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid event_id');
        }
        $orderId = (string) ($d['order_id'] ?? '');
        if (!Uuid::isValid($orderId)) {
            $this->logger->warning('order id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid order_id');
        }
        $tenantId = $env->tenantId !== '' ? $env->tenantId : 'default';

        $this->loyalty->applyOrderFulfilled(new FulfilledInput(
            eventId: $env->eventId,
            orderId: $orderId,
            tenantId: $tenantId,
            customerRef: (string) ($d['customer_ref'] ?? ''),
            totalKopecks: (int) ($d['total_kopecks'] ?? 0),
            occurredAt: self::parseTime($env->occurredAt),
        ));
    }

    private static function parseTime(string $s): \DateTimeImmutable
    {
        if ($s !== '') {
            try {
                return new \DateTimeImmutable($s);
            } catch (\Throwable) {
                // fall through
            }
        }

        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Production\App\PlanService;
use Plushki\Production\Platform\Events\Envelope;
use Plushki\Production\Platform\Events\PoisonException;

/**
 * Accumulates orders.v1.confirmed.# item lines into the day's draft plan. "The
 * day" is the calendar date the confirm event occurred (UTC). Idempotency is
 * enforced at the DB layer via applied_order_lines (event_id, product_id).
 * Outcome: return=ack, PoisonException=drop, throw=requeue.
 */
final class OrdersConsumer
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        if ((string) ($d['status'] ?? '') !== 'confirmed') {
            return; // ack — not a confirm
        }
        if (!Uuid::isValid($env->eventId)) {
            $this->logger->warning('event id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid event_id');
        }
        $occurredAt = self::parseTime($env->occurredAt);

        foreach ((array) ($d['items'] ?? []) as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $pid = (string) ($it['product_id'] ?? '');
            if (!Uuid::isValid($pid)) {
                throw new PoisonException('invalid product_id');
            }
            $this->plans->accumulateConfirmedLine($env->eventId, $occurredAt, $pid, (int) ($it['qty'] ?? 0));
        }
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

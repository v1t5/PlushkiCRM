<?php

declare(strict_types=1);

namespace Plushki\Notifications\Adapters\Events;

use Plushki\Notifications\App\Handler;
use Plushki\Notifications\App\OrderEvent;
use Plushki\Notifications\App\OrderEventItem;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Platform\Events\Envelope;
use Plushki\Notifications\Platform\Events\PoisonException;

/**
 * Adapts an ORDERS-exchange envelope to the app Handler. Maps the envelope to an
 * app.OrderEvent and translates the resulting Outcome onto the generic
 * Consumer's return / Throwable / PoisonException contract:
 *   - Ack  -> return (the Consumer acks),
 *   - Nak  -> throw (the Consumer nacks with requeue),
 *   - Term -> PoisonException (the Consumer nacks without requeue, dropping it).
 *
 * The subject is reconstructed as `<schema>.<tenant_id>`, which is exactly the
 * routing key the relay publishes under.
 */
final class OrdersConsumer
{
    public function __construct(private readonly Handler $handler)
    {
    }

    public function handle(Envelope $env): void
    {
        $items = [];
        foreach (($env->data['items'] ?? []) as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $items[] = new OrderEventItem((string) ($it['name'] ?? ''), (int) ($it['qty'] ?? 0));
        }

        $evt = new OrderEvent(
            eventId: $env->eventId,
            schema: $env->schema,
            subject: $env->schema . '.' . $env->tenantId,
            orderId: (string) ($env->data['order_id'] ?? ''),
            status: (string) ($env->data['status'] ?? ''),
            customerRef: (string) ($env->data['customer_ref'] ?? ''),
            channel: (string) ($env->data['channel'] ?? ''),
            items: $items,
            total: (int) ($env->data['total_kopecks'] ?? 0),
        );

        $this->settle($this->handler->handle($evt));
    }

    private function settle(Outcome $outcome): void
    {
        match ($outcome) {
            Outcome::Ack => null,
            Outcome::Nak => throw new \RuntimeException('retryable: nack-requeue'),
            Outcome::Term => throw new PoisonException('terminal: nack-drop'),
        };
    }
}

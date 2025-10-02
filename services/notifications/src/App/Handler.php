<?php

declare(strict_types=1);

namespace Plushki\Notifications\App;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\DomainException;
use Plushki\Notifications\Domain\ErrorCode;
use Plushki\Notifications\Domain\Notification;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Domain\Recipient;
use Plushki\Notifications\Ports\DeliveryRepo;
use Plushki\Notifications\Ports\Sender;

/**
 * Runs the Notifications use case. Instantiated once and driven from the AMQP
 * consumer worker; no per-request state. The shared dispatch() flow is
 * tryReserve -> send -> rollback so a failed send doesn't leave a dedup row
 * that masks the redelivery.
 */
final class Handler
{
    /** @var array<string, Sender> */
    private readonly array $senders;

    /**
     * @param iterable<Sender> $senders
     */
    public function __construct(
        private readonly DeliveryRepo $deliveries,
        #[AutowireIterator('app.sender')] iterable $senders,
        private readonly LoggerInterface $logger,
    ) {
        $map = [];
        foreach ($senders as $s) {
            $map[$s->channel()->value] = $s;
        }
        $this->senders = $map;
    }

    /**
     * The order-event use case: parse recipient from customer_ref, render body,
     * dispatch.
     */
    public function handle(OrderEvent $e): Outcome
    {
        if ($e->eventId === '' || $e->schema === '') {
            $this->logger->warning('drop malformed event', ['subject' => $e->subject]);

            return Outcome::Term;
        }
        try {
            $rec = Recipient::parse($e->customerRef);
        } catch (DomainException $err) {
            $this->logger->warning('drop event with unsupported recipient', [
                'subject' => $e->subject,
                'customer_ref' => $e->customerRef,
                'err' => $err->getMessage(),
            ]);

            return Outcome::Term;
        }

        return $this->dispatch(new Notification(
            eventId: $e->eventId,
            schema: $e->schema,
            subject: $e->subject,
            recipient: $rec,
            body: self::renderBody($e),
        ));
    }

    /**
     * The inventory.v1.stock_low use case. The recipient is the configured admin
     * chat (passed in by the consumer); if it's empty the event is logged and
     * acked without a send — the alert is opt-in.
     */
    public function handleStockLow(StockLowEvent $e, Recipient $admin): Outcome
    {
        if ($e->eventId === '' || $e->schema === '') {
            $this->logger->warning('drop malformed stock_low', ['subject' => $e->subject]);

            return Outcome::Term;
        }
        if ($admin->id === '') {
            $this->logger->info('admin chat not configured — skipping stock_low alert', [
                'event_id' => $e->eventId,
                'sku' => $e->sku,
            ]);

            return Outcome::Ack;
        }

        return $this->dispatch(new Notification(
            eventId: $e->eventId,
            schema: $e->schema,
            subject: $e->subject,
            recipient: $admin,
            body: self::renderStockLow($e),
        ));
    }

    /**
     * Shared tryReserve -> send -> rollback flow. On a retryable failure we
     * delete the reservation so the next delivery isn't short-circuited as a
     * duplicate.
     */
    private function dispatch(Notification $n): Outcome
    {
        $sender = $this->senders[$n->recipient->channel->value] ?? null;
        if ($sender === null) {
            $this->logger->warning('no sender for channel', ['channel' => $n->recipient->channel->value]);

            return Outcome::Term;
        }

        try {
            $reserved = $this->deliveries->tryReserve(
                $n->eventId,
                $n->schema,
                $n->subject,
                $n->recipient,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Throwable $err) {
            $this->logger->error('reserve delivery', ['event_id' => $n->eventId, 'err' => $err->getMessage()]);

            return Outcome::Nak;
        }
        if (!$reserved) {
            $this->logger->info('duplicate delivery, skipping', ['event_id' => $n->eventId]);

            return Outcome::Ack;
        }

        try {
            $sender->send($n);
        } catch (\Throwable $err) {
            // Roll back the reservation so the redelivery isn't masked as a
            // duplicate. If the rollback itself fails, log loudly but still
            // nak — the alternative (term) loses the message entirely.
            try {
                $this->deliveries->delete($n->eventId);
            } catch (\Throwable $delErr) {
                $this->logger->error('rollback reservation', ['event_id' => $n->eventId, 'err' => $delErr->getMessage()]);
            }
            $sendFailed = $err instanceof DomainException && $err->errorCode === ErrorCode::SendFailed;
            $this->logger->warning($sendFailed ? 'send failed, will retry' : 'send error', [
                'event_id' => $n->eventId,
                'err' => $err->getMessage(),
            ]);

            return Outcome::Nak;
        }

        $this->logger->info('notification sent', [
            'event_id' => $n->eventId,
            'schema' => $n->schema,
            'channel' => $n->recipient->channel->value,
            'recipient' => $n->recipient->id,
        ]);

        return Outcome::Ack;
    }

    /**
     * Rendering is intentionally inline — Phase 1 has three message shapes and a
     * templates package would be premature.
     */
    private static function renderBody(OrderEvent $e): string
    {
        switch ($e->schema) {
            case 'orders.v1.placed':
                $head = sprintf("Order placed (#%s).\nTotal: %s\n", self::shortID($e->orderId), self::formatRubles($e->total));
                if ($e->items === []) {
                    return $head;
                }
                $body = $head . "\nItems:\n";
                foreach ($e->items as $it) {
                    $body .= sprintf("  • %s × %d\n", $it->name, $it->qty);
                }

                return $body;
            case 'orders.v1.confirmed':
                return sprintf('Order #%s is confirmed and being prepared.', self::shortID($e->orderId));
            case 'orders.v1.cancelled':
                return sprintf('Order #%s was cancelled.', self::shortID($e->orderId));
            case 'orders.v1.fulfilled':
                return sprintf('Order #%s is ready. Enjoy!', self::shortID($e->orderId));
        }

        return sprintf('Order #%s: %s', self::shortID($e->orderId), $e->status);
    }

    /**
     * Formats an inventory.v1.stock_low alert. The event payload carries factor +
     * base-unit qty so we present the display unit ("4.5 kg" rather than
     * "4500 g") without a catalog round-trip.
     */
    private static function renderStockLow(StockLowEvent $e): string
    {
        $current = self::formatQty($e->qtyInBase, $e->defaultUnitFactor, $e->defaultUnitCode);
        $threshold = self::formatQty($e->thresholdQtyInBase, $e->defaultUnitFactor, $e->defaultUnitCode);
        $name = $e->name !== '' ? $e->name : $e->sku;

        return sprintf("⚠️ Low stock: %s (%s)\nCurrent: %s\nThreshold: %s", $name, $e->sku, $current, $threshold);
    }

    /**
     * Turns a base-unit qty into the display-unit form. factor <= 0 is treated
     * as 1 to avoid division-by-zero on a malformed event.
     */
    private static function formatQty(int $qtyInBase, int $factor, string $unitCode): string
    {
        if ($factor <= 0) {
            $factor = 1;
        }
        if ($qtyInBase % $factor === 0) {
            return sprintf('%d %s', intdiv($qtyInBase, $factor), $unitCode);
        }
        // Trim trailing zeros via float formatting — fine for display, never
        // used for math.
        $v = $qtyInBase / $factor;

        return sprintf('%g %s', $v, $unitCode);
    }

    private static function shortID(string $id): string
    {
        if (\strlen($id) < 8) {
            return $id;
        }

        return substr($id, -8);
    }

    private static function formatRubles(int $kopecks): string
    {
        return sprintf('%d.%02d ₽', intdiv($kopecks, 100), $kopecks % 100);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Inventory\App\EventLine;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Platform\Events\Envelope;
use Plushki\Inventory\Platform\Events\PoisonException;

/**
 * OrdersConsumer posts a SOLD movement per product line from
 * orders.v1.fulfilled.#. Idempotency is enforced at the DB layer (unique
 * source_event_id index). Products live in "pcs" base units: one product
 * unit = qty in base.
 *
 * WarehouseID is fixed at construction (resolved by the command at startup).
 */
final class OrdersConsumer
{
    public function __construct(
        private readonly MovementService $movements,
        private readonly string $warehouseId,
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
        $occurredAt = self::parseTime($env->occurredAt);

        $lines = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $pid = (string) ($it['product_id'] ?? '');
            if (!Uuid::isValid($pid)) {
                throw new PoisonException('invalid product_id');
            }
            $lines[] = new EventLine(ItemKind::Product, $pid, (int) ($it['qty'] ?? 0));
        }

        $this->movements->applyOrderFulfillment($env->eventId, $this->warehouseId, $occurredAt, $lines);
    }

    private static function parseTime(string $s): ?\DateTimeImmutable
    {
        if ($s === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($s);
        } catch (\Throwable) {
            return null;
        }
    }
}

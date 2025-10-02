<?php

declare(strict_types=1);

namespace Plushki\Notifications\Adapters\Events;

use Plushki\Notifications\App\Handler;
use Plushki\Notifications\App\StockLowEvent;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Domain\Recipient;
use Plushki\Notifications\Platform\Events\Envelope;
use Plushki\Notifications\Platform\Events\PoisonException;

/**
 * Adapts an INVENTORY-exchange stock_low envelope to the app Handler. Alerts
 * route to the configured admin chat (fixed at construction from
 * APP_ADMIN_CHAT_ID); an empty chat id makes the handler ack-and-skip so dev
 * environments without a target don't accumulate redeliveries.
 *
 * Outcome translation matches OrdersConsumer; subject is `<schema>.<tenant_id>`.
 */
final class InventoryConsumer
{
    private readonly Recipient $admin;

    public function __construct(
        private readonly Handler $handler,
        string $adminChatId,
    ) {
        $this->admin = new Recipient(Channel::TG, $adminChatId);
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        $evt = new StockLowEvent(
            eventId: $env->eventId,
            schema: $env->schema,
            subject: $env->schema . '.' . $env->tenantId,
            ingredientId: (string) ($d['ingredient_id'] ?? ''),
            sku: (string) ($d['sku'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            warehouseId: (string) ($d['warehouse_id'] ?? ''),
            qtyInBase: (int) ($d['qty_in_base'] ?? 0),
            thresholdQtyInBase: (int) ($d['threshold_qty_in_base'] ?? 0),
            defaultUnitCode: (string) ($d['default_unit_code'] ?? ''),
            defaultUnitFactor: (int) ($d['default_unit_factor'] ?? 0),
        );

        $this->settle($this->handler->handleStockLow($evt, $this->admin));
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

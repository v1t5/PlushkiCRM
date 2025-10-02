<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

use Plushki\Inventory\Platform\Events\OutboxStore;

/**
 * OutboxRepo is the standard outbox port for events emitted directly (the
 * stock_low alert from the post path). It also satisfies the generic
 * OutboxStore (fetchUnpublished / markPublished) consumed by the relay.
 * MovementRepo carries its own in-transaction event insertion for the
 * movement_posted stream.
 */
interface OutboxRepo extends OutboxStore
{
    public function insert(OutboxEvent $evt): void;
}

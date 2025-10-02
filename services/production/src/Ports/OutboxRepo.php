<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

use Plushki\Production\Platform\Events\OutboxStore;

/**
 * Standard outbox port. The accumulator/publish/FSM paths write their event in
 * the same transaction as the aggregate; insert is reserved for any standalone
 * emission. Also satisfies the generic OutboxStore (fetchUnpublished /
 * markPublished) for the relay.
 */
interface OutboxRepo extends OutboxStore
{
    public function insert(OutboxEvent $evt): void;
}

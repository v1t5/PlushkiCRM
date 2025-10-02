<?php

declare(strict_types=1);

namespace Plushki\Crm\Ports;

use Plushki\Crm\Platform\Events\OutboxStore;

/**
 * OutboxRepo is the standard outbox port. The customer/loyalty write paths take
 * an OutboxEvent inline so the row goes into the same txn. Also satisfies the
 * generic OutboxStore for the relay.
 */
interface OutboxRepo extends OutboxStore
{
    public function insert(OutboxEvent $evt): void;
}

<?php

declare(strict_types=1);

namespace Plushki\Orders\Ports;

use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Platform\Events\OutboxStore;

/**
 * OutboxRepo writes the aggregate row(s) and the matching outbox row in one
 * transaction. It also satisfies the generic OutboxStore (fetchUnpublished /
 * markPublished) consumed by the relay.
 */
interface OutboxRepo extends OutboxStore
{
    public function insertWithOrder(Order $o, OutboxEvent $evt): void;

    public function insertWithStatusChange(string $id, Status $status, \DateTimeImmutable $updatedAt, OutboxEvent $evt): void;
}

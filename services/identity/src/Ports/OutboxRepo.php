<?php

declare(strict_types=1);

namespace Plushki\Identity\Ports;

use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Events\OutboxStore;

/**
 * OutboxRepo is the transactional outbox port. insertWithUser writes the user
 * row and the user_created event in a single transaction so the event is durable
 * iff the user is. It also satisfies the generic OutboxStore (fetchUnpublished /
 * markPublished) consumed by the relay.
 */
interface OutboxRepo extends OutboxStore
{
    public function insertWithUser(User $u, OutboxEvent $evt): void;
}

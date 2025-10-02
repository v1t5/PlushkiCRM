<?php

declare(strict_types=1);

namespace Plushki\Orders\Ports;

use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;

/**
 * OrderRepo is the persistence port for orders. Reads return the aggregate with
 * items attached; writes are atomic at the order+items level.
 */
interface OrderRepo
{
    public function getById(string $id): Order;

    /** @return list<Order> */
    public function listByCustomer(string $tenantId, string $customerRef, int $limit): array;

    /** @return list<Order> */
    public function list(ListFilter $f): array;

    public function updateStatus(string $id, Status $status, \DateTimeImmutable $updatedAt): void;
}

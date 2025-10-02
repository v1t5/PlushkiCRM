<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Fake;

use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Ports\ListFilter;
use Plushki\Orders\Ports\OrderRepo;

/**
 * In-memory OrderRepo. getById throws OrderNotFound for unknown ids; list*()
 * applies the documented filters so usecase filter behaviour can be asserted.
 */
final class FakeOrderRepo implements OrderRepo
{
    /** @var array<string, Order> */
    private array $store = [];

    public function save(Order $o): void
    {
        $this->store[$o->id] = $o;
    }

    public function getById(string $id): Order
    {
        return $this->store[$id]
            ?? throw DomainException::of(ErrorCode::OrderNotFound);
    }

    /** @return list<Order> */
    public function listByCustomer(string $tenantId, string $customerRef, int $limit): array
    {
        $out = [];
        foreach ($this->store as $o) {
            if ($o->tenantId === $tenantId && $o->customerRef === $customerRef) {
                $out[] = $o;
            }
        }

        return \array_slice($out, 0, $limit);
    }

    /** @return list<Order> */
    public function list(ListFilter $f): array
    {
        $out = [];
        foreach ($this->store as $o) {
            if ($o->tenantId !== $f->tenantId) {
                continue;
            }
            if ($f->customerRef !== null && $o->customerRef !== $f->customerRef) {
                continue;
            }
            if ($f->status !== null && $o->status !== $f->status) {
                continue;
            }
            if ($f->channel !== null && $o->channel !== $f->channel) {
                continue;
            }
            if ($f->from !== null && $o->createdAt < $f->from) {
                continue;
            }
            if ($f->to !== null && $o->createdAt >= $f->to) {
                continue;
            }
            $out[] = $o;
        }

        $limit = $f->limit > 0 ? $f->limit : \count($out);

        return \array_slice($out, 0, $limit);
    }

    public function updateStatus(string $id, Status $status, \DateTimeImmutable $updatedAt): void
    {
        $o = $this->getById($id);
        $o->status = $status;
        $o->updatedAt = $updatedAt;
    }
}

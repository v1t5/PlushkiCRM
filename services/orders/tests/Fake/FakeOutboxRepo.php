<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Fake;

use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Platform\Events\OutboxRow;
use Plushki\Orders\Ports\OutboxEvent;
use Plushki\Orders\Ports\OutboxRepo;

/**
 * In-memory OutboxRepo. Records every event written via either insert path and
 * mirrors the saved order into the shared FakeOrderRepo so transitions persist.
 */
final class FakeOutboxRepo implements OutboxRepo
{
    /** @var list<OutboxEvent> */
    public array $events = [];

    /** @var list<array{id: string, status: Status, at: \DateTimeImmutable}> */
    public array $statusChanges = [];

    /** @var list<string> */
    public array $published = [];

    public function __construct(private readonly FakeOrderRepo $orders)
    {
    }

    public function insertWithOrder(Order $o, OutboxEvent $evt): void
    {
        $this->orders->save($o);
        $this->events[] = $evt;
    }

    public function insertWithStatusChange(string $id, Status $status, \DateTimeImmutable $updatedAt, OutboxEvent $evt): void
    {
        $this->orders->updateStatus($id, $status, $updatedAt);
        $this->statusChanges[] = ['id' => $id, 'status' => $status, 'at' => $updatedAt];
        $this->events[] = $evt;
    }

    /** @return list<OutboxRow> */
    public function fetchUnpublished(int $limit): array
    {
        $rows = [];
        foreach (\array_slice($this->events, 0, $limit) as $e) {
            $rows[] = new OutboxRow($e->eventId, $e->schema, $e->tenantId, $e->payload);
        }

        return $rows;
    }

    /** @param list<string> $eventIds */
    public function markPublished(array $eventIds, \DateTimeImmutable $at): void
    {
        foreach ($eventIds as $id) {
            $this->published[] = $id;
        }
    }

    public function lastEvent(): OutboxEvent
    {
        return $this->events[\count($this->events) - 1];
    }
}

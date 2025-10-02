<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Fake;

use Plushki\Inventory\Platform\Events\OutboxRow;
use Plushki\Inventory\Ports\OutboxEvent;
use Plushki\Inventory\Ports\OutboxRepo;

/**
 * Array-backed OutboxRepo. Captures directly-emitted events (stock_low alerts)
 * so tests can assert on what the service published.
 */
final class InMemoryOutboxRepo implements OutboxRepo
{
    /** @var list<OutboxEvent> */
    public array $inserted = [];

    /** @var list<string> */
    public array $published = [];

    public function insert(OutboxEvent $evt): void
    {
        $this->inserted[] = $evt;
    }

    /** @return list<OutboxEvent> events matching the given schema */
    public function withSchema(string $schema): array
    {
        return \array_values(\array_filter($this->inserted, static fn (OutboxEvent $e): bool => $e->schema === $schema));
    }

    /** @return list<OutboxRow> */
    public function fetchUnpublished(int $limit): array
    {
        $out = [];
        foreach ($this->inserted as $e) {
            if (\in_array($e->eventId, $this->published, true)) {
                continue;
            }
            $out[] = new OutboxRow($e->eventId, $e->schema, $e->tenantId, $e->payload);
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param list<string> $eventIds */
    public function markPublished(array $eventIds, \DateTimeImmutable $at): void
    {
        foreach ($eventIds as $id) {
            $this->published[] = $id;
        }
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Fake;

use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Events\OutboxRow;
use Plushki\Identity\Ports\OutboxEvent;
use Plushki\Identity\Ports\OutboxRepo;

/**
 * In-memory OutboxRepo. insertWithUser stores both the user (in the supplied
 * FakeUserRepo, to model the single transaction) and the event row.
 */
final class FakeOutboxRepo implements OutboxRepo
{
    /** @var list<OutboxEvent> */
    public array $events = [];

    /** @var array<string, \DateTimeImmutable> eventId => publishedAt */
    public array $published = [];

    public function __construct(private readonly FakeUserRepo $users)
    {
    }

    public function insertWithUser(User $u, OutboxEvent $evt): void
    {
        $this->users->insert($u);
        $this->events[] = $evt;
    }

    /** @return list<OutboxRow> */
    public function fetchUnpublished(int $limit): array
    {
        $out = [];
        foreach ($this->events as $e) {
            if (isset($this->published[$e->eventId])) {
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
            $this->published[$id] = $at;
        }
    }
}

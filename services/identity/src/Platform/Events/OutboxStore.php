<?php

declare(strict_types=1);

namespace Plushki\Identity\Platform\Events;

/**
 * OutboxStore is the slice of the per-service OutboxRepo that the generic
 * OutboxRelay needs (fetchUnpublished / markPublished). The service's DB outbox
 * repository implements this on top of its own InsertWith<Aggregate>() methods.
 */
interface OutboxStore
{
    /**
     * Fetch unpublished events, oldest-first.
     *
     * @return list<OutboxRow>
     */
    public function fetchUnpublished(int $limit): array;

    /**
     * Mark the given event ids as published at $at.
     *
     * @param list<string> $eventIds
     */
    public function markPublished(array $eventIds, \DateTimeImmutable $at): void;
}

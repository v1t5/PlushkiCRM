<?php

declare(strict_types=1);

namespace Plushki\Catalog\Platform\Events;

/**
 * The slice of the per-service OutboxRepo that the generic OutboxRelay needs.
 * The service's DB outbox repository implements this on top of its own insert
 * methods.
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

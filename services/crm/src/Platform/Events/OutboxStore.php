<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Events;

/**
 * OutboxStore is the slice of the per-service OutboxRepo that the generic
 * OutboxRelay needs (fetchUnpublished / markPublished).
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

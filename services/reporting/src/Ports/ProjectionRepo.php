<?php

declare(strict_types=1);

namespace Plushki\Reporting\Ports;

/**
 * Single port the consumers + read endpoints call through. Apply* return
 * alreadyApplied=true when the envelope has been seen before (PK collision on
 * applied_events). Read methods return rows already shaped for the JSON response.
 */
interface ProjectionRepo
{
    public function applyFulfilled(FulfilledIn $in): bool;

    public function applyStockLow(StockLowIn $in): bool;

    public function applyMovementPosted(MovementPostedIn $in): bool;

    /** @return list<array<string, mixed>> */
    public function listSalesDaily(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return list<array<string, mixed>> */
    public function listSalesByChannel(string $tenantId, \DateTimeImmutable $day): array;

    /** @return list<array<string, mixed>> */
    public function listTopItems(string $tenantId, \DateTimeImmutable $day, int $limit, string $orderBy): array;

    /** @return list<array<string, mixed>> */
    public function listStockLowEvents(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array;

    /** @return array{waste_qty_in_base: int, deduction_qty_in_base: int, percentage: float} */
    public function wasteSummary(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return list<array<string, mixed>> */
    public function listWaste(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array;
}

<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Support;

use Plushki\Reporting\Ports\FulfilledIn;
use Plushki\Reporting\Ports\MovementPostedIn;
use Plushki\Reporting\Ports\ProjectionRepo;
use Plushki\Reporting\Ports\StockLowIn;

/**
 * In-memory ProjectionRepo used to capture the DTO a consumer maps an envelope
 * into. No database — read methods return empty arrays.
 */
final class FakeProjectionRepo implements ProjectionRepo
{
    public ?FulfilledIn $fulfilled = null;
    public ?StockLowIn $stockLow = null;
    public ?MovementPostedIn $movement = null;

    public function applyFulfilled(FulfilledIn $in): bool
    {
        $this->fulfilled = $in;

        return false;
    }

    public function applyStockLow(StockLowIn $in): bool
    {
        $this->stockLow = $in;

        return false;
    }

    public function applyMovementPosted(MovementPostedIn $in): bool
    {
        $this->movement = $in;

        return false;
    }

    public function listSalesDaily(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return [];
    }

    public function listSalesByChannel(string $tenantId, \DateTimeImmutable $day): array
    {
        return [];
    }

    public function listTopItems(string $tenantId, \DateTimeImmutable $day, int $limit, string $orderBy): array
    {
        return [];
    }

    public function listStockLowEvents(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        return [];
    }

    public function wasteSummary(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return ['waste_qty_in_base' => 0, 'deduction_qty_in_base' => 0, 'percentage' => 0.0];
    }

    public function listWaste(string $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        return [];
    }
}

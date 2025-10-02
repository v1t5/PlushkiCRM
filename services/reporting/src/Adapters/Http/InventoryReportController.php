<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Plushki\Reporting\Ports\ProjectionRepo;

/** Maps the /v1/inventory read endpoints (low-stock events, waste percentage, waste rows). */
#[Route('/v1/inventory')]
final class InventoryReportController
{
    public function __construct(private readonly ProjectionRepo $repo)
    {
    }

    #[Route('/low-stock-events', methods: ['GET'])]
    public function lowStockEvents(Request $request): Response
    {
        [$from, $to] = Api::fromTo($request);
        // stock_low_events has TIMESTAMPTZ occurred_at — extend 'to' to end-of-day.
        $to = $to->modify('+1 day')->modify('-1 microsecond');
        $limit = Api::limit($request, 100);

        return Api::json(['items' => $this->repo->listStockLowEvents(Api::tenantFrom($request), $from, $to, $limit)]);
    }

    #[Route('/waste-percentage', methods: ['GET'])]
    public function wastePercentage(Request $request): Response
    {
        [$from, $to] = Api::fromTo($request);
        $s = $this->repo->wasteSummary(Api::tenantFrom($request), $from, $to);

        return Api::json([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'waste_qty_in_base' => $s['waste_qty_in_base'],
            'deduction_qty_in_base' => $s['deduction_qty_in_base'],
            'percentage' => $s['percentage'],
        ]);
    }

    #[Route('/waste', methods: ['GET'])]
    public function waste(Request $request): Response
    {
        [$from, $to] = Api::fromTo($request);
        $limit = Api::limit($request, 100);

        return Api::json(['items' => $this->repo->listWaste(Api::tenantFrom($request), $from, $to, $limit)]);
    }
}

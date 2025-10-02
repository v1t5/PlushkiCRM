<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Plushki\Reporting\Ports\ProjectionRepo;

/** Maps the /v1/sales read endpoints. */
#[Route('/v1/sales')]
final class SalesController
{
    public function __construct(private readonly ProjectionRepo $repo)
    {
    }

    #[Route('/daily', methods: ['GET'])]
    public function daily(Request $request): Response
    {
        [$from, $to] = Api::fromTo($request);

        return Api::json(['items' => $this->repo->listSalesDaily(Api::tenantFrom($request), $from, $to)]);
    }

    #[Route('/by-channel', methods: ['GET'])]
    public function byChannel(Request $request): Response
    {
        $date = Api::singleDate($request, 'date');

        return Api::json(['items' => $this->repo->listSalesByChannel(Api::tenantFrom($request), $date)]);
    }

    #[Route('/top-items', methods: ['GET'])]
    public function topItems(Request $request): Response
    {
        $date = Api::singleDate($request, 'date');
        $limit = Api::limit($request, 10);
        $orderBy = (string) $request->query->get('order_by', '');

        return Api::json(['items' => $this->repo->listTopItems(Api::tenantFrom($request), $date, $limit, $orderBy)]);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Plushki\Production\App\PlanService;

/**
 * Maps /v1/plans.
 */
#[Route('/v1/plans')]
final class PlanController
{
    public function __construct(private readonly PlanService $plans)
    {
    }

    #[Route('/{date}', methods: ['GET'])]
    public function get(string $date): Response
    {
        [$plan, $items] = $this->plans->getByDate(Api::parseDate($date));

        return Api::json(Resp::plan($plan, $items));
    }

    #[Route('/{date}/publish', methods: ['POST'])]
    public function publish(string $date): Response
    {
        [$plan, $tasks] = $this->plans->publish(Api::parseDate($date));

        return Api::json([
            'plan' => Resp::plan($plan, []),
            'tasks' => array_map(Resp::task(...), $tasks),
        ]);
    }
}

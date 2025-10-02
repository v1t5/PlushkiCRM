<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Inventory\Adapters\Http\Dto\CreateWarehouseReq;
use Plushki\Inventory\App\WarehouseService;

/**
 * WarehouseController maps /v1/warehouses.
 */
#[Route('/v1/warehouses')]
final class WarehouseController
{
    public function __construct(
        private readonly WarehouseService $warehouses,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, CreateWarehouseReq::class);
        $w = $this->warehouses->create($req->code, $req->name);

        return Api::json(Resp::warehouse($w), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $whs = $this->warehouses->list(Api::tenantFrom($request));

        return Api::json(['items' => array_map(Resp::warehouse(...), $whs)]);
    }
}

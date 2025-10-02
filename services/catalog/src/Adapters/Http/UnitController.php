<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Catalog\Adapters\Http\Dto\CreateUnitReq;
use Plushki\Catalog\App\UnitService;

/** Maps /v1/units. */
#[Route('/v1/units')]
final class UnitController
{
    public function __construct(
        private readonly UnitService $units,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, CreateUnitReq::class);
        $baseId = $req->base_unit_id !== null ? Api::validUuid($req->base_unit_id, 'base_unit_id') : null;
        $u = $this->units->create($req->code, $req->name, $baseId, $req->factor);

        return Api::json(Resp::unit($u), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $units = $this->units->list(Api::tenantFrom($request));

        return Api::json(['items' => array_map(Resp::unit(...), $units)]);
    }
}
<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Catalog\Adapters\Http\Dto\CreateIngredientReq;
use Plushki\Catalog\App\IngredientService;

/** Maps /v1/ingredients. */
#[Route('/v1/ingredients')]
final class IngredientController
{
    public function __construct(
        private readonly IngredientService $ingredients,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, CreateIngredientReq::class);
        $unitId = Api::validUuid($req->default_unit_id, 'default_unit_id');
        $i = $this->ingredients->create($req->sku, $req->name, $unitId, $req->low_stock_threshold_qty);

        return Api::json(Resp::ingredient($i), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $ings = $this->ingredients->list(Api::tenantFrom($request));

        return Api::json(['items' => array_map(Resp::ingredient(...), $ings)]);
    }
}
<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Inventory\Adapters\Http\Dto\PostMovementReq;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\App\PostMovementInput;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;

/**
 * MovementController maps /v1/movements — a port of the PostMovement handler.
 */
#[Route('/v1/movements')]
final class MovementController
{
    public function __construct(
        private readonly MovementService $movements,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function post(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, PostMovementReq::class);
        $whId = Api::validUuid($req->warehouse_id, 'warehouse_id');
        $itemId = Api::validUuid($req->item_id, 'item_id');

        [$mv, $lvl] = $this->movements->post(new PostMovementInput(
            warehouseId: $whId,
            itemKind: ItemKind::from($req->item_kind),
            itemId: $itemId,
            type: MovementType::from($req->type),
            qtyInBase: $req->qty_in_base,
            reason: $req->reason,
        ));

        return Api::json([
            'movement' => Resp::movement($mv),
            'level' => Resp::level($lvl),
        ], 201);
    }
}

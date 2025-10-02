<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Plushki\Inventory\App\StockService;
use Plushki\Inventory\Domain\ItemKind;

/**
 * StockController maps /v1/stock — a port of the ListStock handler.
 */
#[Route('/v1/stock')]
final class StockController
{
    public function __construct(private readonly StockService $stock)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $whIdRaw = (string) $request->query->get('warehouse_id', '');
        $whId = $whIdRaw !== '' ? Api::validUuid($whIdRaw, 'warehouse_id') : null;

        $kind = null;
        $kindRaw = (string) $request->query->get('item_kind', '');
        if ($kindRaw !== '') {
            if (!ItemKind::isValid($kindRaw)) {
                throw Api::validationFailed('item_kind');
            }
            $kind = ItemKind::from($kindRaw);
        }

        $levels = $this->stock->list(Api::tenantFrom($request), $whId, $kind);

        return Api::json(['items' => array_map(Resp::level(...), $levels)]);
    }
}

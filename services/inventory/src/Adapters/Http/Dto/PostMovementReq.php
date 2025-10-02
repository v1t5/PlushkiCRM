<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request body for POST /v1/movements. qty_in_base is a signed magnitude; zero
 * is rejected by the domain (InvalidQty → 400). The controller re-validates the
 * UUIDs and maps the enums.
 */
final class PostMovementReq
{
    #[Assert\NotBlank]
    public string $warehouse_id = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ingredient', 'product'])]
    public string $item_kind = '';

    #[Assert\NotBlank]
    public string $item_id = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['IN', 'OUT', 'WASTE', 'ADJUST', 'CONSUMED_BY_PRODUCTION', 'SOLD'])]
    public string $type = '';

    #[Assert\Type('integer')]
    public int $qty_in_base = 0;

    #[Assert\Length(max: 500)]
    public string $reason = '';
}

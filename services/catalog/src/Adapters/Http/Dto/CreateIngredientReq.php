<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /v1/ingredients request body. */
final class CreateIngredientReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 64)]
    public string $sku = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $default_unit_id = '';

    #[Assert\GreaterThanOrEqual(0)]
    public int $low_stock_threshold_qty = 0;
}

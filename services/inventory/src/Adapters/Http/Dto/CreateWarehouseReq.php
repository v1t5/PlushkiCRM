<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request body for POST /v1/warehouses.
 */
final class CreateWarehouseReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 32)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $name = '';
}

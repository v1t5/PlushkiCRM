<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /v1/units request body. */
final class CreateUnitReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 32)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $name = '';

    #[Assert\Uuid]
    public ?string $base_unit_id = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public int $factor = 0;
}

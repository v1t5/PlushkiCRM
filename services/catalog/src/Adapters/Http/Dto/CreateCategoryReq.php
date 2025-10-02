<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /v1/categories request body. */
final class CreateCategoryReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 64)]
    public string $slug = '';

    public int $sort_order = 0;
}

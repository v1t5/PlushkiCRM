<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /v1/products request body. */
final class CreateProductReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 64)]
    public string $sku = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public string $description = '';

    #[Assert\GreaterThanOrEqual(0)]
    public int $price_kopecks = 0;

    #[Assert\Uuid]
    public ?string $category_id = null;
}

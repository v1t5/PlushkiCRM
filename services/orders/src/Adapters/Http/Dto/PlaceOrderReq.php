<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request body for POST /v1/orders. Items are kept as raw associative arrays
 * ({product_id, qty}); the controller validates each entry's UUID + qty before
 * building PlaceItem inputs.
 */
final class PlaceOrderReq
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['tg', 'pos', 'web'])]
    public string $channel = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    public string $customer_ref = '';

    /** @var list<array<string, mixed>> */
    #[Assert\Count(min: 1)]
    public array $items = [];
}
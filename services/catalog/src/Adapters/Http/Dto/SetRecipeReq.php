<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http\Dto;

/**
 * PUT /v1/products/{id}/recipe request body. Lines are kept as raw associative
 * arrays ({ingredient_id, qty, unit_id}); the controller validates each entry's
 * UUIDs and qty before building inputs.
 */
final class SetRecipeReq
{
    /** @var list<array<string, mixed>> */
    public array $lines = [];
}

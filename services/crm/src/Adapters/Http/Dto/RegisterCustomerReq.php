<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /v1/customers request body. Identities are optional; the controller
 * validates each entry's type/value before building RegisterIdentity inputs.
 */
final class RegisterCustomerReq
{
    #[Assert\Length(max: 64)]
    public string $tenant_id = '';

    #[Assert\Length(max: 200)]
    public string $display_name = '';

    /** @var list<array<string, mixed>> */
    public array $identities = [];
}

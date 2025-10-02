<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /auth/introspect request body. */
final class IntrospectReq
{
    #[Assert\NotBlank]
    public string $token = '';
}

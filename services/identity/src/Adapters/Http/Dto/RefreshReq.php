<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /auth/refresh request body. */
final class RefreshReq
{
    #[Assert\NotBlank]
    public string $refresh_token = '';
}

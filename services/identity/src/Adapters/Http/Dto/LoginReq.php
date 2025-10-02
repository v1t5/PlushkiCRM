<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /auth/login request body. */
final class LoginReq
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    public string $password = '';
}

<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /auth/register request body. */
final class RegisterReq
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password = '';

    #[Assert\NotBlank]
    public string $display_name = '';
}

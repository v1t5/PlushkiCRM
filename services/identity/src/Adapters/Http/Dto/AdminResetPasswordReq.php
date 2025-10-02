<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /admin/users/{id}/password request body. */
final class AdminResetPasswordReq
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password = '';
}

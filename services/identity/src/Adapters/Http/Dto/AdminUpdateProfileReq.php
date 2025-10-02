<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /admin/users/{id} request body. */
final class AdminUpdateProfileReq
{
    #[Assert\NotBlank]
    public string $display_name = '';
}

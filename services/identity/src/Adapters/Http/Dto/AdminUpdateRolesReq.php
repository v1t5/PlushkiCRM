<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /admin/users/{id}/roles request body. */
final class AdminUpdateRolesReq
{
    /** @var list<string> */
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\NotBlank()])]
    public array $roles = [];
}

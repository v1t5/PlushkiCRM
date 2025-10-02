<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /admin/service-tokens request body. */
final class AdminCreateTokenReq
{
    #[Assert\NotBlank]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['bot', 'service'])]
    public string $actor_type = '';

    /** @var list<string> */
    public array $scopes = [];
}

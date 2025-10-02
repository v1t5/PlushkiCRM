<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Plushki\Identity\Domain\User;
use Plushki\Identity\Ports\UserRepo;

/** MeService backs GET /me. */
final class MeService
{
    public function __construct(private readonly UserRepo $users)
    {
    }

    public function get(string $id): User
    {
        return $this->users->getById($id);
    }
}

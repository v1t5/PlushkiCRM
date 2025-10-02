<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Plushki\Identity\Domain\ServiceToken;
use Plushki\Identity\Ports\ServiceTokenRepo;

/**
 * CreateService creates a new service token and returns the plaintext secret
 * (shown to the operator once at creation; not recoverable).
 */
final class CreateService
{
    public function __construct(private readonly ServiceTokenRepo $repo)
    {
    }

    /**
     * @param list<string> $scopes
     * @return array{0: ServiceToken, 1: string} [token, plaintext]
     */
    public function create(string $name, string $actorType, array $scopes): array
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('name is required');
        }
        [$t, $plain] = ServiceToken::issue($name, $actorType, $scopes);
        $this->repo->insert($t);

        return [$t, $plain];
    }
}

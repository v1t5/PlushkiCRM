<?php

declare(strict_types=1);

namespace Plushki\Identity\Ports;

use Plushki\Identity\Domain\ServiceToken;

/**
 * ServiceTokenRepo persists service tokens. Introspection scans listActive() and
 * argon2-compares, because the token ID is not carried in the plaintext.
 */
interface ServiceTokenRepo
{
    public function insert(ServiceToken $t): void;

    public function getById(string $id): ServiceToken;

    /** @return list<ServiceToken> */
    public function listActive(): array;

    public function touchLastUsed(string $id, \DateTimeImmutable $at): void;
}

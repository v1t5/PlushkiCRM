<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Fake;

use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\ServiceToken;
use Plushki\Identity\Ports\ServiceTokenRepo;

/**
 * In-memory ServiceTokenRepo. listActive() returns non-revoked tokens, matching
 * the introspection scan in the real adapter. touchLastUsed records the call.
 */
final class FakeServiceTokenRepo implements ServiceTokenRepo
{
    /** @var array<string, ServiceToken> keyed by id */
    public array $byId = [];

    /** @var array<string, \DateTimeImmutable> id => lastUsedAt */
    public array $touched = [];

    public function insert(ServiceToken $t): void
    {
        $this->byId[$t->id] = $t;
    }

    public function getById(string $id): ServiceToken
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::ServiceTokenInvalid);
        }

        return $this->byId[$id];
    }

    /** @return list<ServiceToken> */
    public function listActive(): array
    {
        $out = [];
        foreach ($this->byId as $t) {
            if (!$t->isRevoked()) {
                $out[] = $t;
            }
        }

        return array_values($out);
    }

    public function touchLastUsed(string $id, \DateTimeImmutable $at): void
    {
        $this->touched[$id] = $at;
    }
}

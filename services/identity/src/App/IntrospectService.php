<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Ports\ServiceTokenRepo;

/**
 * IntrospectService validates opaque service tokens by linear scan. Slow by
 * design (argon2id is the protection), so the gateway caches hits, not this.
 */
final class IntrospectService
{
    public function __construct(private readonly ServiceTokenRepo $repo)
    {
    }

    /** @throws DomainException ServiceTokenInvalid */
    public function introspect(string $plaintext): IntrospectResult
    {
        if ($plaintext === '') {
            throw DomainException::of(ErrorCode::ServiceTokenInvalid);
        }
        foreach ($this->repo->listActive() as $t) {
            if ($t->verify($plaintext)) {
                $this->repo->touchLastUsed($t->id, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

                return new IntrospectResult($t->actorType, $t->id, $t->tenantId, $t->scopes);
            }
        }

        throw DomainException::of(ErrorCode::ServiceTokenInvalid);
    }
}

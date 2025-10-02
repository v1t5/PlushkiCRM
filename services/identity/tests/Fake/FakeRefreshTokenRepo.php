<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Fake;

use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Ports\RefreshTokenRepo;

/**
 * In-memory RefreshTokenRepo. getByHash misses throw
 * DomainException(RefreshTokenInvalid); markUsedAndInsert is atomic in the real
 * adapter and modelled here as a single mutate + insert.
 */
final class FakeRefreshTokenRepo implements RefreshTokenRepo
{
    /** @var array<string, RefreshToken> keyed by id */
    public array $byId = [];

    public function insert(RefreshToken $t): void
    {
        $this->byId[$t->id] = $t;
    }

    public function getByHash(string $hash): RefreshToken
    {
        foreach ($this->byId as $t) {
            if ($t->tokenHash === $hash) {
                return $t;
            }
        }
        throw DomainException::of(ErrorCode::RefreshTokenInvalid);
    }

    public function markUsed(string $id, \DateTimeImmutable $at): void
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::RefreshTokenInvalid);
        }
        $this->byId[$id] = $this->withUsedAt($this->byId[$id], $at);
    }

    public function markUsedAndInsert(string $oldId, \DateTimeImmutable $at, RefreshToken $next): void
    {
        $this->markUsed($oldId, $at);
        $this->insert($next);
    }

    private function withUsedAt(RefreshToken $t, \DateTimeImmutable $at): RefreshToken
    {
        return new RefreshToken(
            $t->id,
            $t->tenantId,
            $t->userId,
            $t->tokenHash,
            $t->expiresAt,
            $t->createdAt,
            $at,
            $t->revokedAt,
        );
    }
}

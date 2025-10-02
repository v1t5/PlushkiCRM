<?php

declare(strict_types=1);

namespace Plushki\Identity\Ports;

use Plushki\Identity\Domain\RefreshToken;

/**
 * RefreshTokenRepo persists and rotates refresh tokens. markUsedAndInsert is
 * transactional: the old token is consumed and the new one inserted in the same
 * DB tx so a crash mid-flight cannot leave the user double-issued.
 */
interface RefreshTokenRepo
{
    public function insert(RefreshToken $t): void;

    public function getByHash(string $hash): RefreshToken;

    public function markUsed(string $id, \DateTimeImmutable $at): void;

    public function markUsedAndInsert(string $oldId, \DateTimeImmutable $at, RefreshToken $next): void;
}

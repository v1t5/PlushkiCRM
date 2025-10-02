<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Ports\RefreshTokenRepo as RefreshTokenRepoPort;

/**
 * RefreshTokenRepo is the DBAL refresh-token store. markUsedAndInsert rotates in
 * a single transaction so a crash mid-flight never leaves the caller with two
 * valid tokens or zero.
 */
final class RefreshTokenRepo implements RefreshTokenRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(RefreshToken $t): void
    {
        $this->insertRow($this->db, $t);
    }

    public function getByHash(string $hash): RefreshToken
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, user_id, token_hash, expires_at, created_at, used_at, revoked_at
             FROM refresh_tokens WHERE token_hash = :hash',
            ['hash' => $hash],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::RefreshTokenInvalid);
        }

        return self::scan($row);
    }

    public function markUsed(string $id, \DateTimeImmutable $at): void
    {
        $this->db->executeStatement(
            'UPDATE refresh_tokens SET used_at = CAST(:at AS timestamptz)
             WHERE id = CAST(:id AS uuid) AND used_at IS NULL',
            ['id' => $id, 'at' => Ts::fmt($at)],
        );
    }

    public function markUsedAndInsert(string $oldId, \DateTimeImmutable $at, RefreshToken $next): void
    {
        $this->db->transactional(function (Connection $tx) use ($oldId, $at, $next): void {
            $affected = $tx->executeStatement(
                'UPDATE refresh_tokens SET used_at = CAST(:at AS timestamptz)
                 WHERE id = CAST(:id AS uuid) AND used_at IS NULL AND revoked_at IS NULL',
                ['id' => $oldId, 'at' => Ts::fmt($at)],
            );
            if ($affected === 0) {
                throw DomainException::of(ErrorCode::RefreshTokenUsed);
            }
            $this->insertRow($tx, $next);
        });
    }

    private function insertRow(Connection $conn, RefreshToken $t): void
    {
        $conn->executeStatement(
            'INSERT INTO refresh_tokens (id, tenant_id, user_id, token_hash, expires_at, created_at)
             VALUES (CAST(:id AS uuid), :tenant_id, CAST(:user_id AS uuid), :token_hash,
                     CAST(:expires_at AS timestamptz), CAST(:created_at AS timestamptz))',
            [
                'id' => $t->id,
                'tenant_id' => $t->tenantId,
                'user_id' => $t->userId,
                'token_hash' => $t->tokenHash,
                'expires_at' => Ts::fmt($t->expiresAt),
                'created_at' => Ts::fmt($t->createdAt),
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): RefreshToken
    {
        return new RefreshToken(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            userId: (string) $row['user_id'],
            tokenHash: (string) $row['token_hash'],
            expiresAt: Ts::parse((string) $row['expires_at']),
            createdAt: Ts::parse((string) $row['created_at']),
            usedAt: $row['used_at'] !== null ? Ts::parse((string) $row['used_at']) : null,
            revokedAt: $row['revoked_at'] !== null ? Ts::parse((string) $row['revoked_at']) : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\ServiceToken;
use Plushki\Identity\Ports\ServiceTokenRepo as ServiceTokenRepoPort;

/** ServiceTokenRepo is the DBAL service-token store. listActive() feeds the introspection linear scan. */
final class ServiceTokenRepo implements ServiceTokenRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(ServiceToken $t): void
    {
        $this->db->executeStatement(
            'INSERT INTO service_tokens (id, tenant_id, name, actor_type, scopes, token_hash, created_at)
             VALUES (CAST(:id AS uuid), :tenant_id, :name, :actor_type, CAST(:scopes AS text[]),
                     :token_hash, CAST(:created_at AS timestamptz))',
            [
                'id' => $t->id,
                'tenant_id' => $t->tenantId,
                'name' => $t->name,
                'actor_type' => $t->actorType,
                'scopes' => PgArray::encode($t->scopes),
                'token_hash' => $t->tokenHash,
                'created_at' => Ts::fmt($t->createdAt),
            ],
        );
    }

    public function getById(string $id): ServiceToken
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, name, actor_type, scopes, token_hash, created_at, revoked_at, last_used_at
             FROM service_tokens WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::ServiceTokenInvalid);
        }

        return self::scan($row);
    }

    public function listActive(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, name, actor_type, scopes, token_hash, created_at, revoked_at, last_used_at
             FROM service_tokens WHERE revoked_at IS NULL',
        );

        return array_map(self::scan(...), $rows);
    }

    public function touchLastUsed(string $id, \DateTimeImmutable $at): void
    {
        $this->db->executeStatement(
            'UPDATE service_tokens SET last_used_at = CAST(:at AS timestamptz) WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'at' => Ts::fmt($at)],
        );
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): ServiceToken
    {
        return new ServiceToken(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            name: (string) $row['name'],
            actorType: (string) $row['actor_type'],
            scopes: PgArray::decode($row['scopes'] !== null ? (string) $row['scopes'] : null),
            tokenHash: (string) $row['token_hash'],
            createdAt: Ts::parse((string) $row['created_at']),
            revokedAt: $row['revoked_at'] !== null ? Ts::parse((string) $row['revoked_at']) : null,
            lastUsedAt: $row['last_used_at'] !== null ? Ts::parse((string) $row['last_used_at']) : null,
        );
    }
}

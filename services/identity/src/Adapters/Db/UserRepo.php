<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Ports\UserListParams;
use Plushki\Identity\Ports\UserRepo as UserRepoPort;

/**
 * UserRepo is the DBAL implementation of the user persistence port.
 * Hand-written SQL, no ORM. UUID, text[] and timestamptz columns are bound as
 * text with explicit casts (pdo_pgsql has no native binding for them).
 */
final class UserRepo implements UserRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(User $u): void
    {
        try {
            $this->db->executeStatement(
                'INSERT INTO users (id, tenant_id, email, password_hash, display_name, roles, created_at)
                 VALUES (CAST(:id AS uuid), :tenant_id, :email, :password_hash, :display_name,
                         CAST(:roles AS text[]), CAST(:created_at AS timestamptz))',
                [
                    'id' => $u->id,
                    'tenant_id' => $u->tenantId,
                    'email' => $u->email,
                    'password_hash' => $u->passwordHash,
                    'display_name' => $u->displayName,
                    'roles' => PgArray::encode($u->roles),
                    'created_at' => Ts::fmt($u->createdAt),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::EmailAlreadyTaken);
        }
    }

    public function getByEmail(string $tenantId, string $email): User
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, email, password_hash, display_name, roles, created_at, archived_at
             FROM users WHERE tenant_id = :tenant AND email = :email',
            ['tenant' => $tenantId, 'email' => strtolower($email)],
        );

        return self::scan($row);
    }

    public function getById(string $id): User
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, email, password_hash, display_name, roles, created_at, archived_at
             FROM users WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );

        return self::scan($row);
    }

    public function updateRoles(string $id, array $roles): void
    {
        $this->affectOne(
            'UPDATE users SET roles = CAST(:roles AS text[]) WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'roles' => PgArray::encode($roles)],
        );
    }

    public function updateProfile(string $id, string $displayName): void
    {
        $this->affectOne(
            'UPDATE users SET display_name = :dn WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'dn' => $displayName],
        );
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $this->affectOne(
            'UPDATE users SET password_hash = :ph WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'ph' => $passwordHash],
        );
    }

    public function setArchived(string $id, ?\DateTimeImmutable $at): void
    {
        $this->affectOne(
            'UPDATE users SET archived_at = CAST(:at AS timestamptz) WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'at' => $at !== null ? Ts::fmt($at) : null],
        );
    }

    public function list(UserListParams $p): array
    {
        $limit = ($p->limit <= 0 || $p->limit > 500) ? 100 : $p->limit;
        $offset = max(0, $p->offset);
        $tenant = $p->tenantId !== '' ? $p->tenantId : 'default';

        $params = ['tenant' => $tenant, 'limit' => $limit, 'offset' => $offset];
        $where = 'WHERE tenant_id = :tenant';
        if (!$p->includeArchived) {
            $where .= ' AND archived_at IS NULL';
        }
        if (($q = trim($p->q)) !== '') {
            $params['q'] = '%' . strtolower($q) . '%';
            $where .= ' AND (LOWER(email) LIKE :q OR LOWER(display_name) LIKE :q)';
        }

        $rows = $this->db->fetchAllAssociative(
            "SELECT id, tenant_id, email, password_hash, display_name, roles, created_at, archived_at
             FROM users {$where} ORDER BY created_at DESC
             LIMIT CAST(:limit AS integer) OFFSET CAST(:offset AS integer)",
            $params,
        );

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $params */
    private function affectOne(string $sql, array $params): void
    {
        if ($this->db->executeStatement($sql, $params) === 0) {
            throw DomainException::of(ErrorCode::UserNotFound);
        }
    }

    /** @param array<string, mixed>|false $row */
    private static function scan(array|false $row): User
    {
        if ($row === false) {
            throw DomainException::of(ErrorCode::UserNotFound);
        }

        return new User(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            displayName: (string) $row['display_name'],
            roles: PgArray::decode($row['roles'] !== null ? (string) $row['roles'] : null),
            createdAt: Ts::parse((string) $row['created_at']),
            archivedAt: $row['archived_at'] !== null ? Ts::parse((string) $row['archived_at']) : null,
        );
    }
}

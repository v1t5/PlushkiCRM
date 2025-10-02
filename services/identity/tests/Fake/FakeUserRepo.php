<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Fake;

use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Ports\UserListParams;
use Plushki\Identity\Ports\UserRepo;

/**
 * In-memory UserRepo backed by arrays. Lookups that miss throw
 * DomainException(UserNotFound), matching the real DBAL adapter contract.
 */
final class FakeUserRepo implements UserRepo
{
    /** @var array<string, User> keyed by id */
    public array $byId = [];

    public function add(User $u): void
    {
        $this->byId[$u->id] = $u;
    }

    public function insert(User $u): void
    {
        $this->byId[$u->id] = $u;
    }

    public function getByEmail(string $tenantId, string $email): User
    {
        foreach ($this->byId as $u) {
            if ($u->tenantId === $tenantId && $u->email === $email) {
                return $u;
            }
        }
        throw DomainException::of(ErrorCode::UserNotFound);
    }

    public function getById(string $id): User
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::UserNotFound);
        }

        return $this->byId[$id];
    }

    /** @param list<string> $roles */
    public function updateRoles(string $id, array $roles): void
    {
        $u = $this->getById($id);
        $this->byId[$id] = new User(
            $u->id,
            $u->tenantId,
            $u->email,
            $u->passwordHash,
            $u->displayName,
            $roles,
            $u->createdAt,
            $u->archivedAt,
        );
    }

    public function updateProfile(string $id, string $displayName): void
    {
        $u = $this->getById($id);
        $this->byId[$id] = new User(
            $u->id,
            $u->tenantId,
            $u->email,
            $u->passwordHash,
            $displayName,
            $u->roles,
            $u->createdAt,
            $u->archivedAt,
        );
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $u = $this->getById($id);
        $this->byId[$id] = new User(
            $u->id,
            $u->tenantId,
            $u->email,
            $passwordHash,
            $u->displayName,
            $u->roles,
            $u->createdAt,
            $u->archivedAt,
        );
    }

    public function setArchived(string $id, ?\DateTimeImmutable $at): void
    {
        $u = $this->getById($id);
        $this->byId[$id] = new User(
            $u->id,
            $u->tenantId,
            $u->email,
            $u->passwordHash,
            $u->displayName,
            $u->roles,
            $u->createdAt,
            $at,
        );
    }

    /** @return list<User> */
    public function list(UserListParams $p): array
    {
        $out = [];
        foreach ($this->byId as $u) {
            if ($u->tenantId !== $p->tenantId) {
                continue;
            }
            if (!$p->includeArchived && $u->isArchived()) {
                continue;
            }
            if ($p->q !== '') {
                $needle = strtolower($p->q);
                $hay = strtolower($u->email . ' ' . $u->displayName);
                if (!str_contains($hay, $needle)) {
                    continue;
                }
            }
            $out[] = $u;
        }

        return array_values($out);
    }
}

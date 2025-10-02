<?php

declare(strict_types=1);

namespace Plushki\Identity\Ports;

use Plushki\Identity\Domain\User;

/**
 * UserRepo is the persistence port for users. The app layer depends on this
 * interface; the DBAL implementation lives in Adapters\Db. Lookups that miss
 * throw DomainException(UserNotFound).
 */
interface UserRepo
{
    public function insert(User $u): void;

    public function getByEmail(string $tenantId, string $email): User;

    public function getById(string $id): User;

    /** @param list<string> $roles */
    public function updateRoles(string $id, array $roles): void;

    public function updateProfile(string $id, string $displayName): void;

    public function updatePassword(string $id, string $passwordHash): void;

    public function setArchived(string $id, ?\DateTimeImmutable $at): void;

    /** @return list<User> */
    public function list(UserListParams $p): array;
}

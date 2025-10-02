<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Ports\UserListParams;
use Plushki\Identity\Ports\UserRepo;

/**
 * UserAdminService backs the admin user-management endpoints. It publishes no
 * events: admin edits (display name, roles, password reset, archive) are local
 * config changes. Creating a brand-new user still goes through
 * AuthService::register so user_created fires.
 */
final class UserAdminService
{
    public function __construct(private readonly UserRepo $users)
    {
    }

    /** @return list<User> */
    public function list(UserListParams $p): array
    {
        return $this->users->list($p);
    }

    public function get(string $id): User
    {
        return $this->users->getById($id);
    }

    /** Updates display_name only. Email is immutable post-creation. */
    public function updateProfile(string $id, string $displayName): User
    {
        $this->users->updateProfile($id, $displayName);

        return $this->users->getById($id);
    }

    /**
     * Replace the role set. Empty list is rejected — every user must carry at
     * least one role so JWT claims stay non-empty.
     *
     * @param list<string> $roles
     * @throws DomainException InvalidRole
     */
    public function updateRoles(string $id, array $roles): User
    {
        $clean = [];
        foreach ($roles as $r) {
            if ($r === '') {
                continue;
            }
            if (!User::isAllowedRole($r)) {
                throw DomainException::of(ErrorCode::InvalidRole);
            }
            if (!\in_array($r, $clean, true)) {
                $clean[] = $r;
            }
        }
        if ($clean === []) {
            throw DomainException::of(ErrorCode::InvalidRole);
        }
        $this->users->updateRoles($id, $clean);

        return $this->users->getById($id);
    }

    /** Overwrite the bcrypt hash; the new password is length-validated. */
    public function resetPassword(string $id, string $newPassword): void
    {
        $hash = User::hashPassword($newPassword);
        $this->users->updatePassword($id, $hash);
    }

    /** Flip archived_at. Archived users cannot log in. */
    public function setArchived(string $id, bool $archived): User
    {
        $at = $archived ? new \DateTimeImmutable('now', new \DateTimeZone('UTC')) : null;
        $this->users->setArchived($id, $at);

        return $this->users->getById($id);
    }
}

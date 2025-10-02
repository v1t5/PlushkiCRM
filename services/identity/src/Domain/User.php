<?php

declare(strict_types=1);

namespace Plushki\Identity\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * User is the persistent end-user identity. Email is unique per tenant. Roles
 * default to ["user"]. passwordHash carries the bcrypt result — never the
 * plaintext.
 *
 * Pure domain: no Symfony, no SQL. Constructors validate and hash; the app
 * layer receives pre-validated aggregates.
 */
final class User
{
    public const DEFAULT_TENANT = 'default';
    public const DEFAULT_USER_ROLE = 'user';
    public const ADMIN_ROLE = 'admin';
    public const BCRYPT_COST = 10;
    public const MIN_PASSWORD_LEN = 8;

    /** Every role the admin UI may assign — keeps the JWT roles[] claim bounded. */
    public const ALLOWED_ROLES = [self::DEFAULT_USER_ROLE, self::ADMIN_ROLE, 'baker', 'cashier'];

    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $passwordHash,
        public string $displayName,
        public array $roles,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    /**
     * NewUser validates the inputs, hashes the password, and returns a User
     * ready to insert. tenant defaults to 'default' (single-tenant v1).
     *
     * @throws DomainException InvalidEmail | PasswordTooShort
     */
    public static function create(string $email, string $password, string $displayName): self
    {
        $email = self::normaliseEmailOrThrow($email);
        if (\strlen($password) < self::MIN_PASSWORD_LEN) {
            throw DomainException::of(ErrorCode::PasswordTooShort);
        }

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            email: $email,
            passwordHash: self::hashPassword($password),
            displayName: trim($displayName),
            roles: [self::DEFAULT_USER_ROLE],
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @throws DomainException PasswordTooShort */
    public static function hashPassword(string $plain): string
    {
        if (\strlen($plain) < self::MIN_PASSWORD_LEN) {
            throw DomainException::of(ErrorCode::PasswordTooShort);
        }

        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /** @throws DomainException InvalidEmail */
    public static function normaliseEmailOrThrow(string $email): string
    {
        $email = strtolower(trim($email));
        if (!self::looksLikeEmail($email)) {
            throw DomainException::of(ErrorCode::InvalidEmail);
        }

        return $email;
    }

    public static function isAllowedRole(string $role): bool
    {
        return \in_array($role, self::ALLOWED_ROLES, true);
    }

    /** Constant-time bcrypt comparison. Throws InvalidCredentials on mismatch. */
    public function verifyPassword(string $plain): void
    {
        if (!password_verify($plain, $this->passwordHash)) {
            throw DomainException::of(ErrorCode::InvalidCredentials);
        }
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    private static function looksLikeEmail(string $s): bool
    {
        $at = strpos($s, '@');
        if ($at === false || $at === 0 || $at === \strlen($s) - 1) {
            return false;
        }

        return strpos(substr($s, $at + 1), '.') !== false;
    }
}

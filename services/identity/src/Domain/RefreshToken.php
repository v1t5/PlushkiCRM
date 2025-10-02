<?php

declare(strict_types=1);

namespace Plushki\Identity\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * RefreshToken is the persisted opaque refresh token. Only the SHA-256 hash of
 * the secret lives in the DB; the plaintext is shown to the client once.
 */
final class RefreshToken
{
    public const TTL_DAYS = 30;
    public const ACCESS_TTL_SECONDS = 15 * 60;

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $userId,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $usedAt = null,
        public ?\DateTimeImmutable $revokedAt = null,
    ) {
    }

    /**
     * issue returns a fresh refresh token plus the plaintext secret to hand
     * back to the caller. The secret is never persisted.
     *
     * @return array{0: self, 1: string} [token, plaintext]
     */
    public static function issue(string $userId): array
    {
        $plaintext = self::randomBase64Url(32);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $token = new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: User::DEFAULT_TENANT,
            userId: $userId,
            tokenHash: self::hash($plaintext),
            expiresAt: $now->modify('+' . self::TTL_DAYS . ' days'),
            createdAt: $now,
        );

        return [$token, $plaintext];
    }

    /** Canonical SHA-256 hex digest, used for both insert and lookup. */
    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * isValid throws the matching DomainException if the token can no longer be
     * exchanged (revoked / used / expired). The DB layer does the lookup; this
     * is the in-memory state check.
     *
     * @throws DomainException
     */
    public function ensureValid(\DateTimeImmutable $now): void
    {
        if ($this->revokedAt !== null) {
            throw DomainException::of(ErrorCode::RefreshTokenInvalid);
        }
        if ($this->usedAt !== null) {
            throw DomainException::of(ErrorCode::RefreshTokenUsed);
        }
        if ($now > $this->expiresAt) {
            throw DomainException::of(ErrorCode::RefreshTokenExpired);
        }
    }

    private static function randomBase64Url(int $n): string
    {
        return rtrim(strtr(base64_encode(random_bytes($n)), '+/', '-_'), '=');
    }
}

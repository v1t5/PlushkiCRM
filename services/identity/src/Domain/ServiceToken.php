<?php

declare(strict_types=1);

namespace Plushki\Identity\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * ServiceToken is a long-lived bearer token for a non-human caller (bot,
 * internal service). Hashed with argon2id because it lives forever and the hash
 * must resist offline brute-force if leaked. The plaintext (`tst_<base32>`) is
 * shown once.
 */
final class ServiceToken
{
    public const PREFIX = 'tst_';

    // argon2id parameters: m=64MiB, t=1, p=2.
    private const ARGON_OPTS = [
        'memory_cost' => 64 * 1024,
        'time_cost' => 1,
        'threads' => 2,
    ];

    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public string $actorType,
        public array $scopes,
        public string $tokenHash,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $revokedAt = null,
        public ?\DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    /**
     * issue returns a new service token plus the opaque secret to display once.
     *
     * @param list<string> $scopes
     * @return array{0: self, 1: string} [token, plaintext]
     * @throws \InvalidArgumentException for an unknown actor type
     */
    public static function issue(string $name, string $actorType, array $scopes): array
    {
        if ($actorType !== 'bot' && $actorType !== 'service') {
            throw new \InvalidArgumentException("actor_type must be 'bot' or 'service'");
        }
        $plaintext = self::PREFIX . self::base32NoPadding(random_bytes(32));
        $token = new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: User::DEFAULT_TENANT,
            name: trim($name),
            actorType: $actorType,
            scopes: array_values($scopes),
            tokenHash: password_hash($plaintext, PASSWORD_ARGON2ID, self::ARGON_OPTS),
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        return [$token, $plaintext];
    }

    /** Constant-time argon2id verification. */
    public function verify(string $plaintext): bool
    {
        return password_verify($plaintext, $this->tokenHash);
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /** RFC 4648 base32 (upper-case, no padding). */
    private static function base32NoPadding(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(\ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $alphabet[(int) bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }
}

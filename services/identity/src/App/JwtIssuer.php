<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Firebase\JWT\JWT;
use Symfony\Component\Uid\Uuid;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Domain\User;

/**
 * JwtIssuer signs RS256 access tokens and exposes the public key as a JWK so
 * the gateway can validate without round-tripping back to identity.
 *
 * Claims (architecture.md → End-user auth): sub, tenant_id, roles[], iat, exp,
 * iss, jti. Signed RS256, `kid` in the header.
 */
final class JwtIssuer
{
    private function __construct(
        private readonly string $privatePem,
        private readonly string $publicPem,
        private readonly string $kid,
        private readonly string $issuer,
    ) {
    }

    /** Load a PEM-encoded RSA private key from disk. */
    public static function fromPem(string $path, string $kid, string $issuer): self
    {
        $pem = file_get_contents($path);
        if ($pem === false) {
            throw new \RuntimeException("read jwt key: {$path}");
        }

        return self::fromPemString($pem, $kid, $issuer);
    }

    public static function fromPemString(string $pem, string $kid, string $issuer): self
    {
        $priv = openssl_pkey_get_private($pem);
        if ($priv === false) {
            throw new \RuntimeException('jwt: invalid RSA private key');
        }
        $details = openssl_pkey_get_details($priv);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new \RuntimeException('jwt: not an RSA private key');
        }

        return new self($pem, $details['key'], $kid, $issuer);
    }

    /**
     * Generate a 2048-bit RSA key in-process. Use only when env == "dev" —
     * issued tokens become invalid on every process restart.
     */
    public static function ephemeral(string $kid, string $issuer): self
    {
        return self::fromPemString(self::newPrivatePem(), $kid, $issuer);
    }

    /** Generate a fresh 2048-bit RSA private key as a PEM string. */
    public static function newPrivatePem(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            throw new \RuntimeException('generate rsa key failed');
        }
        openssl_pkey_export($res, $privatePem);

        return $privatePem;
    }

    /**
     * Issue a signed RS256 access JWT for the given user.
     *
     * @return array{0: string, 1: \DateTimeImmutable} [token, expiry]
     */
    public function issueAccess(User $u): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $exp = $now->modify('+' . RefreshToken::ACCESS_TTL_SECONDS . ' seconds');

        $payload = [
            'iss' => $this->issuer,
            'sub' => $u->id,
            'iat' => $now->getTimestamp(),
            'exp' => $exp->getTimestamp(),
            'jti' => Uuid::v4()->toRfc4122(),
            'tenant_id' => $u->tenantId,
            'roles' => $u->roles,
        ];

        $token = JWT::encode($payload, $this->privatePem, 'RS256', $this->kid);

        return [$token, $exp];
    }

    /** RFC 7517 JWK set with the single signing public key. */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicPem));
        $n = self::b64url($details['rsa']['n']);
        $e = self::b64url($details['rsa']['e']);

        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $this->kid,
                'n' => $n,
                'e' => $e,
            ]],
        ];
    }

    public function publicPem(): string
    {
        return $this->publicPem;
    }

    public function keyId(): string
    {
        return $this->kid;
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}

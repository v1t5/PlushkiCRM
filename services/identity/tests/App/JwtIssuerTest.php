<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use Plushki\Identity\App\JwtIssuer;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Domain\User;

final class JwtIssuerTest extends TestCase
{
    private JwtIssuer $issuer;

    protected function setUp(): void
    {
        // Real RS256 — no infra needed; generate an in-process key.
        $this->issuer = JwtIssuer::fromPemString(JwtIssuer::newPrivatePem(), 'kid-1', 'identity');
    }

    private function user(): User
    {
        return new User('user-123', 'default', 'a@b.com', 'h', 'Alice', ['admin', 'baker'], new \DateTimeImmutable('now'));
    }

    public function testIssueAccessReturnsTokenAndExpiry(): void
    {
        [$token, $exp] = $this->issuer->issueAccess($this->user());

        self::assertNotSame('', $token);
        self::assertInstanceOf(\DateTimeImmutable::class, $exp);
        // expiry ~ ACCESS_TTL_SECONDS out
        $delta = $exp->getTimestamp() - time();
        self::assertEqualsWithDelta(RefreshToken::ACCESS_TTL_SECONDS, $delta, 5);
    }

    public function testIssuedTokenVerifiesWithPublicKeyAndCarriesClaims(): void
    {
        [$token] = $this->issuer->issueAccess($this->user());

        $decoded = (array) JWT::decode($token, new Key($this->issuer->publicPem(), 'RS256'));

        self::assertSame('identity', $decoded['iss']);
        self::assertSame('user-123', $decoded['sub']);
        self::assertSame('default', $decoded['tenant_id']);
        self::assertSame(['admin', 'baker'], (array) $decoded['roles']);
        self::assertArrayHasKey('jti', $decoded);
        self::assertArrayHasKey('iat', $decoded);
        self::assertArrayHasKey('exp', $decoded);
        self::assertGreaterThan($decoded['iat'], $decoded['exp']);
    }

    public function testTokenHeaderCarriesKid(): void
    {
        [$token] = $this->issuer->issueAccess($this->user());
        $header = json_decode(self::b64urlDecode(explode('.', $token)[0]), true);

        self::assertSame('RS256', $header['alg']);
        self::assertSame('kid-1', $header['kid']);
    }

    public function testJwksExposesSinglePublicKey(): void
    {
        $jwks = $this->issuer->jwks();

        self::assertArrayHasKey('keys', $jwks);
        self::assertCount(1, $jwks['keys']);
        $k = $jwks['keys'][0];
        self::assertSame('RSA', $k['kty']);
        self::assertSame('sig', $k['use']);
        self::assertSame('RS256', $k['alg']);
        self::assertSame('kid-1', $k['kid']);
        self::assertNotSame('', $k['n']);
        self::assertNotSame('', $k['e']);
    }

    public function testKeyIdAccessor(): void
    {
        self::assertSame('kid-1', $this->issuer->keyId());
    }

    public function testFromPemStringRejectsNonRsaGarbage(): void
    {
        $this->expectException(\RuntimeException::class);
        JwtIssuer::fromPemString('not a pem key', 'kid', 'identity');
    }

    private static function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Domain\User;

final class RefreshTokenTest extends TestCase
{
    public function testIssueReturnsTokenAndPlaintext(): void
    {
        [$token, $plain] = RefreshToken::issue('user-1');

        self::assertInstanceOf(RefreshToken::class, $token);
        self::assertNotSame('', $plain);
        self::assertSame('user-1', $token->userId);
        self::assertSame(User::DEFAULT_TENANT, $token->tenantId);
        self::assertNull($token->usedAt);
        self::assertNull($token->revokedAt);
        self::assertNotSame('', $token->id);
    }

    public function testIssueStoresOnlyHashNotPlaintext(): void
    {
        [$token, $plain] = RefreshToken::issue('user-1');

        self::assertNotSame($plain, $token->tokenHash, 'plaintext must not be persisted');
        self::assertSame(RefreshToken::hash($plain), $token->tokenHash);
        self::assertSame(64, \strlen($token->tokenHash), 'sha256 hex digest is 64 chars');
    }

    public function testIssueExpiryIsThirtyDaysOut(): void
    {
        [$token] = RefreshToken::issue('user-1');

        $expectedMin = (new \DateTimeImmutable('+29 days 23 hours'))->getTimestamp();
        $expectedMax = (new \DateTimeImmutable('+30 days 1 hour'))->getTimestamp();
        self::assertGreaterThan($expectedMin, $token->expiresAt->getTimestamp());
        self::assertLessThan($expectedMax, $token->expiresAt->getTimestamp());
    }

    public function testIssueProducesUniquePlaintextEachTime(): void
    {
        [, $a] = RefreshToken::issue('user-1');
        [, $b] = RefreshToken::issue('user-1');
        self::assertNotSame($a, $b);
    }

    public function testHashIsDeterministic(): void
    {
        self::assertSame(RefreshToken::hash('abc'), RefreshToken::hash('abc'));
        self::assertNotSame(RefreshToken::hash('abc'), RefreshToken::hash('abd'));
    }

    public function testEnsureValidPassesForFreshToken(): void
    {
        [$token] = RefreshToken::issue('user-1');
        $token->ensureValid(new \DateTimeImmutable('now'));
        self::assertTrue(true, 'fresh token is valid');
    }

    public function testEnsureValidRejectsRevoked(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $token = new RefreshToken(
            'id', 'default', 'u', 'h',
            $now->modify('+10 days'), $now,
            null, $now, // revokedAt set
        );
        $this->assertCode(ErrorCode::RefreshTokenInvalid, fn () => $token->ensureValid($now));
    }

    public function testEnsureValidRejectsUsed(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $token = new RefreshToken(
            'id', 'default', 'u', 'h',
            $now->modify('+10 days'), $now,
            $now, // usedAt set
        );
        $this->assertCode(ErrorCode::RefreshTokenUsed, fn () => $token->ensureValid($now));
    }

    public function testEnsureValidRejectsExpired(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $token = new RefreshToken(
            'id', 'default', 'u', 'h',
            $now->modify('-1 second'), $now->modify('-10 days'),
        );
        $this->assertCode(ErrorCode::RefreshTokenExpired, fn () => $token->ensureValid($now));
    }

    public function testRevokedTakesPrecedenceOverUsed(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $token = new RefreshToken(
            'id', 'default', 'u', 'h',
            $now->modify('+10 days'), $now,
            $now, $now, // both used and revoked
        );
        $this->assertCode(ErrorCode::RefreshTokenInvalid, fn () => $token->ensureValid($now));
    }

    private function assertCode(ErrorCode $expected, callable $fn): void
    {
        try {
            $fn();
            self::fail('expected DomainException ' . $expected->value);
        } catch (DomainException $e) {
            self::assertSame($expected, $e->errorCode);
        }
    }
}

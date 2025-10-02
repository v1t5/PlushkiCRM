<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plushki\Identity\Domain\ServiceToken;
use Plushki\Identity\Domain\User;

final class ServiceTokenTest extends TestCase
{
    #[DataProvider('actorTypes')]
    public function testIssueHappyPath(string $actorType): void
    {
        [$token, $plain] = ServiceToken::issue('  my-bot  ', $actorType, ['read', 'write']);

        self::assertSame('my-bot', $token->name, 'name trimmed');
        self::assertSame($actorType, $token->actorType);
        self::assertSame(['read', 'write'], $token->scopes);
        self::assertSame(User::DEFAULT_TENANT, $token->tenantId);
        self::assertNull($token->revokedAt);
        self::assertNull($token->lastUsedAt);
        self::assertNotSame('', $token->id);
    }

    /** @return iterable<string, array{string}> */
    public static function actorTypes(): iterable
    {
        yield 'bot' => ['bot'];
        yield 'service' => ['service'];
    }

    public function testIssuePlaintextHasPrefixAndVerifies(): void
    {
        [$token, $plain] = ServiceToken::issue('bot', 'bot', []);

        self::assertStringStartsWith(ServiceToken::PREFIX, $plain);
        self::assertNotSame($plain, $token->tokenHash, 'argon2id hash, not plaintext');
        self::assertTrue($token->verify($plain));
        self::assertFalse($token->verify('tst_wrong'));
    }

    public function testIssueUsesArgon2id(): void
    {
        [$token] = ServiceToken::issue('bot', 'bot', []);
        $info = password_get_info($token->tokenHash);
        self::assertSame(PASSWORD_ARGON2ID, $info['algo']);
    }

    public function testIssueRejectsUnknownActorType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceToken::issue('bot', 'human', []);
    }

    public function testScopesReindexed(): void
    {
        [$token] = ServiceToken::issue('bot', 'bot', [2 => 'a', 5 => 'b']);
        self::assertSame(['a', 'b'], $token->scopes);
    }

    public function testIsRevoked(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $active = new ServiceToken('id', 'default', 'n', 'bot', [], 'h', $now);
        self::assertFalse($active->isRevoked());

        $revoked = new ServiceToken('id', 'default', 'n', 'bot', [], 'h', $now, $now);
        self::assertTrue($revoked->isRevoked());
    }

    public function testTwoTokensProduceDifferentPlaintext(): void
    {
        [, $a] = ServiceToken::issue('bot', 'bot', []);
        [, $b] = ServiceToken::issue('bot', 'bot', []);
        self::assertNotSame($a, $b);
    }
}

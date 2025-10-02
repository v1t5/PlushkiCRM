<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Identity\App\CreateService;
use Plushki\Identity\App\IntrospectResult;
use Plushki\Identity\App\IntrospectService;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\ServiceToken;
use Plushki\Identity\Tests\Fake\FakeServiceTokenRepo;

final class ServiceTokenUseCasesTest extends TestCase
{
    private FakeServiceTokenRepo $repo;
    private CreateService $create;
    private IntrospectService $introspect;

    protected function setUp(): void
    {
        $this->repo = new FakeServiceTokenRepo();
        $this->create = new CreateService($this->repo);
        $this->introspect = new IntrospectService($this->repo);
    }

    public function testCreatePersistsAndReturnsPlaintext(): void
    {
        [$token, $plain] = $this->create->create('my-bot', 'bot', ['read']);

        self::assertSame('my-bot', $token->name);
        self::assertStringStartsWith(ServiceToken::PREFIX, $plain);
        self::assertArrayHasKey($token->id, $this->repo->byId);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->create->create('   ', 'bot', []);
    }

    public function testCreateRejectsBadActorType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->create->create('bot', 'human', []);
    }

    public function testIntrospectFindsMatchingToken(): void
    {
        [$token, $plain] = $this->create->create('my-bot', 'service', ['orders:read', 'orders:write']);

        $result = $this->introspect->introspect($plain);

        self::assertInstanceOf(IntrospectResult::class, $result);
        self::assertSame('service', $result->actorType);
        self::assertSame($token->id, $result->actorId);
        self::assertSame($token->tenantId, $result->tenantId);
        self::assertSame(['orders:read', 'orders:write'], $result->scopes);
        // last-used touched as a side effect
        self::assertArrayHasKey($token->id, $this->repo->touched);
    }

    public function testIntrospectRejectsEmptyString(): void
    {
        $this->assertCode(ErrorCode::ServiceTokenInvalid, fn () => $this->introspect->introspect(''));
    }

    public function testIntrospectRejectsUnknownToken(): void
    {
        $this->create->create('my-bot', 'bot', []);
        $this->assertCode(ErrorCode::ServiceTokenInvalid, fn () => $this->introspect->introspect('tst_nonexistent'));
    }

    public function testIntrospectSkipsRevokedTokens(): void
    {
        [$token, $plain] = $this->create->create('my-bot', 'bot', []);
        // revoke it: replace with a revoked copy so listActive() drops it
        $this->repo->byId[$token->id] = new ServiceToken(
            $token->id, $token->tenantId, $token->name, $token->actorType,
            $token->scopes, $token->tokenHash, $token->createdAt,
            new \DateTimeImmutable('now'),
        );

        $this->assertCode(ErrorCode::ServiceTokenInvalid, fn () => $this->introspect->introspect($plain));
        self::assertArrayNotHasKey($token->id, $this->repo->touched, 'revoked token not touched');
    }

    public function testIntrospectPicksCorrectTokenAmongMany(): void
    {
        $this->create->create('bot-a', 'bot', ['a']);
        [$wanted, $plain] = $this->create->create('bot-b', 'service', ['b']);
        $this->create->create('bot-c', 'bot', ['c']);

        $result = $this->introspect->introspect($plain);
        self::assertSame($wanted->id, $result->actorId);
        self::assertSame(['b'], $result->scopes);
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

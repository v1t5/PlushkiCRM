<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Identity\App\AuthService;
use Plushki\Identity\App\JwtIssuer;
use Plushki\Identity\App\TokenPair;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Tests\Fake\FakeOutboxRepo;
use Plushki\Identity\Tests\Fake\FakeRefreshTokenRepo;
use Plushki\Identity\Tests\Fake\FakeUserRepo;

final class AuthServiceTest extends TestCase
{
    private FakeUserRepo $users;
    private FakeRefreshTokenRepo $refresh;
    private FakeOutboxRepo $outbox;
    private AuthService $svc;

    protected function setUp(): void
    {
        $this->users = new FakeUserRepo();
        $this->refresh = new FakeRefreshTokenRepo();
        $this->outbox = new FakeOutboxRepo($this->users);
        $jwt = JwtIssuer::fromPemString(JwtIssuer::newPrivatePem(), 'test-kid', 'identity-test');
        $this->svc = new AuthService($this->users, $this->refresh, $this->outbox, $jwt);
    }

    public function testRegisterPersistsUserAndOutboxAndReturnsPair(): void
    {
        [$user, $pair] = $this->svc->register('Bob@Example.com', 'password123', 'Bob');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('bob@example.com', $user->email);
        // user persisted via outbox transaction
        self::assertArrayHasKey($user->id, $this->users->byId);
        // one outbox event with the user_created schema
        self::assertCount(1, $this->outbox->events);
        self::assertSame('identity.v1.user_created', $this->outbox->events[0]->schema);
        self::assertSame($user->id, $this->outbox->events[0]->aggregateId);
        self::assertSame('user', $this->outbox->events[0]->aggregateType);
        // refresh token inserted
        self::assertCount(1, $this->refresh->byId);
        // pair populated
        self::assertInstanceOf(TokenPair::class, $pair);
        self::assertNotSame('', $pair->accessToken);
        self::assertNotSame('', $pair->refreshToken);
    }

    public function testRegisterEnvelopePayloadCarriesUserFields(): void
    {
        [$user] = $this->svc->register('bob@example.com', 'password123', 'Bob');

        $payload = json_decode($this->outbox->events[0]->payload, true);
        self::assertSame('identity.v1.user_created', $payload['schema']);
        self::assertSame($user->id, $payload['data']['user_id']);
        self::assertSame('bob@example.com', $payload['data']['email']);
        self::assertSame(['user'], $payload['data']['roles']);
        self::assertSame(['type' => 'system', 'id' => 'identity'], $payload['actor']);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $this->assertCode(ErrorCode::InvalidEmail, fn () => $this->svc->register('nope', 'password123', 'X'));
        self::assertCount(0, $this->outbox->events, 'no event on validation failure');
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->assertCode(ErrorCode::PasswordTooShort, fn () => $this->svc->register('a@b.com', 'short', 'X'));
    }

    public function testLoginHappyPath(): void
    {
        $this->svc->register('a@b.com', 'password123', 'Alice');
        [$user, $pair] = $this->svc->login('a@b.com', 'password123');

        self::assertSame('a@b.com', $user->email);
        self::assertInstanceOf(TokenPair::class, $pair);
        // a fresh refresh token was issued (2 total: register + login)
        self::assertCount(2, $this->refresh->byId);
    }

    public function testLoginUnknownUserReportsInvalidCredentials(): void
    {
        // do NOT leak user existence: UserNotFound -> InvalidCredentials
        $this->assertCode(ErrorCode::InvalidCredentials, fn () => $this->svc->login('ghost@b.com', 'password123'));
    }

    public function testLoginWrongPasswordReportsInvalidCredentials(): void
    {
        $this->svc->register('a@b.com', 'password123', 'Alice');
        $this->assertCode(ErrorCode::InvalidCredentials, fn () => $this->svc->login('a@b.com', 'wrong-password'));
    }

    public function testLoginArchivedUserReportsInvalidCredentials(): void
    {
        [$user] = $this->svc->register('a@b.com', 'password123', 'Alice');
        $this->users->setArchived($user->id, new \DateTimeImmutable('now'));
        $this->assertCode(ErrorCode::InvalidCredentials, fn () => $this->svc->login('a@b.com', 'password123'));
    }

    public function testRefreshRotatesToken(): void
    {
        [, $pair] = $this->svc->register('a@b.com', 'password123', 'Alice');
        $oldPlain = $pair->refreshToken;

        [$user, $next] = $this->svc->refresh($oldPlain);

        self::assertSame('a@b.com', $user->email);
        self::assertNotSame($oldPlain, $next->refreshToken, 'rotated to a new secret');
        // old token now consumed (usedAt set)
        $old = $this->refresh->getByHash(RefreshToken::hash($oldPlain));
        self::assertNotNull($old->usedAt);
    }

    public function testRefreshCanOnlyBeUsedOnce(): void
    {
        [, $pair] = $this->svc->register('a@b.com', 'password123', 'Alice');
        $oldPlain = $pair->refreshToken;

        $this->svc->refresh($oldPlain);
        // second use of the same token is rejected as used
        $this->assertCode(ErrorCode::RefreshTokenUsed, fn () => $this->svc->refresh($oldPlain));
    }

    public function testRefreshUnknownTokenReportsInvalid(): void
    {
        $this->assertCode(ErrorCode::RefreshTokenInvalid, fn () => $this->svc->refresh('not-a-real-token'));
    }

    public function testRefreshExpiredTokenReportsExpired(): void
    {
        [$user] = $this->svc->register('a@b.com', 'password123', 'Alice');
        // craft an already-expired token directly in the repo
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $plain = 'expired-secret';
        $expired = new RefreshToken(
            'rt-expired', 'default', $user->id, RefreshToken::hash($plain),
            $now->modify('-1 second'), $now->modify('-31 days'),
        );
        $this->refresh->insert($expired);

        $this->assertCode(ErrorCode::RefreshTokenExpired, fn () => $this->svc->refresh($plain));
    }

    public function testRefreshArchivedUserRejected(): void
    {
        [$user, $pair] = $this->svc->register('a@b.com', 'password123', 'Alice');
        $this->users->setArchived($user->id, new \DateTimeImmutable('now'));

        $this->assertCode(ErrorCode::UserArchived, fn () => $this->svc->refresh($pair->refreshToken));
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

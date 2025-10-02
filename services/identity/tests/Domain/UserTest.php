<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;

final class UserTest extends TestCase
{
    public function testCreateHappyPathNormalisesAndHashes(): void
    {
        $u = User::create('  Alice@Example.COM ', 'password123', '  Alice  ');

        self::assertSame('alice@example.com', $u->email, 'email lowered + trimmed');
        self::assertSame('Alice', $u->displayName, 'display name trimmed');
        self::assertSame(User::DEFAULT_TENANT, $u->tenantId);
        self::assertSame([User::DEFAULT_USER_ROLE], $u->roles);
        self::assertNull($u->archivedAt);
        self::assertNotSame('password123', $u->passwordHash, 'plaintext must never be stored');
        self::assertTrue(password_verify('password123', $u->passwordHash));
        self::assertNotSame('', $u->id);
        self::assertEqualsWithDelta(time(), $u->createdAt->getTimestamp(), 5);
    }

    #[DataProvider('invalidEmails')]
    public function testCreateRejectsInvalidEmail(string $email): void
    {
        try {
            User::create($email, 'password123', 'X');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidEmail, $e->errorCode);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'no at' => ['plainstring'];
        yield 'leading at' => ['@example.com'];
        yield 'trailing at' => ['alice@'];
        yield 'no dot in domain' => ['alice@localhost'];
        yield 'empty' => [''];
    }

    public function testCreateRejectsShortPassword(): void
    {
        try {
            User::create('a@b.com', '1234567', 'X');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::PasswordTooShort, $e->errorCode);
        }
    }

    public function testCreateAcceptsMinLengthPassword(): void
    {
        $u = User::create('a@b.com', '12345678', 'X');
        self::assertTrue(password_verify('12345678', $u->passwordHash));
    }

    public function testHashPasswordRejectsShort(): void
    {
        $this->expectException(DomainException::class);
        try {
            User::hashPassword('short');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::PasswordTooShort, $e->errorCode);
            throw $e;
        }
    }

    public function testHashPasswordUsesBcryptCost10(): void
    {
        $hash = User::hashPassword('password123');
        $info = password_get_info($hash);
        self::assertSame(PASSWORD_BCRYPT, $info['algo']);
        self::assertSame(User::BCRYPT_COST, $info['options']['cost']);
    }

    public function testVerifyPasswordSucceedsSilently(): void
    {
        $u = User::create('a@b.com', 'password123', 'X');
        $u->verifyPassword('password123');
        self::assertTrue(true, 'no exception thrown on correct password');
    }

    public function testVerifyPasswordThrowsInvalidCredentials(): void
    {
        $u = User::create('a@b.com', 'password123', 'X');
        try {
            $u->verifyPassword('wrong-password');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidCredentials, $e->errorCode);
        }
    }

    #[DataProvider('roleCases')]
    public function testIsAllowedRole(string $role, bool $expected): void
    {
        self::assertSame($expected, User::isAllowedRole($role));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function roleCases(): iterable
    {
        yield 'user' => ['user', true];
        yield 'admin' => ['admin', true];
        yield 'baker' => ['baker', true];
        yield 'cashier' => ['cashier', true];
        yield 'unknown' => ['superuser', false];
        yield 'empty' => ['', false];
        yield 'case sensitive' => ['Admin', false];
    }

    public function testHasRole(): void
    {
        $u = new User('id', 'default', 'a@b.com', 'h', 'X', ['admin', 'baker'], new \DateTimeImmutable());
        self::assertTrue($u->hasRole('admin'));
        self::assertTrue($u->hasRole('baker'));
        self::assertFalse($u->hasRole('user'));
    }

    public function testIsArchived(): void
    {
        $active = new User('id', 'default', 'a@b.com', 'h', 'X', ['user'], new \DateTimeImmutable());
        self::assertFalse($active->isArchived());

        $archived = new User('id', 'default', 'a@b.com', 'h', 'X', ['user'], new \DateTimeImmutable(), new \DateTimeImmutable());
        self::assertTrue($archived->isArchived());
    }

    public function testNormaliseEmailOrThrowReturnsCanonical(): void
    {
        self::assertSame('a@b.com', User::normaliseEmailOrThrow('  A@B.COM '));
    }
}

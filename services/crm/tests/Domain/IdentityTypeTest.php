<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Domain;

use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\IdentityType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, IdentityType}>
     */
    public static function validProvider(): iterable
    {
        yield 'tg' => ['tg', IdentityType::TG];
        yield 'phone' => ['phone', IdentityType::Phone];
        yield 'email' => ['email', IdentityType::Email];
        yield 'pos_walkin' => ['pos_walkin', IdentityType::PosWalkin];
    }

    #[DataProvider('validProvider')]
    public function testParseAcceptsValidValues(string $input, IdentityType $expected): void
    {
        self::assertSame($expected, IdentityType::parse($input));
    }

    public function testBackingValues(): void
    {
        self::assertSame('tg', IdentityType::TG->value);
        self::assertSame('phone', IdentityType::Phone->value);
        self::assertSame('email', IdentityType::Email->value);
        self::assertSame('pos_walkin', IdentityType::PosWalkin->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['whatsapp'];
        yield 'wrong-case' => ['TG'];
        yield 'pos-not-walkin' => ['pos'];
        yield 'numeric' => ['42'];
    }

    #[DataProvider('invalidProvider')]
    public function testParseRejectsInvalidValues(string $input): void
    {
        try {
            IdentityType::parse($input);
            self::fail('expected DomainException for ' . $input);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidIdentityType, $e->errorCode);
        }
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        self::assertNull(IdentityType::tryFrom('nope'));
    }
}

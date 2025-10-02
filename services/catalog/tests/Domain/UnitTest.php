<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitTest extends TestCase
{
    public function testCreateBaseUnit(): void
    {
        $u = Unit::create('  G  ', '  Gram  ', null, 1);

        self::assertSame('g', $u->code, 'code is trimmed and lower-cased');
        self::assertSame('Gram', $u->name);
        self::assertNull($u->baseUnitId);
        self::assertSame(1, $u->factor);
        self::assertTrue($u->isBase());
        self::assertFalse($u->isArchived());
        self::assertSame(Unit::DEFAULT_TENANT, $u->tenantId);
    }

    public function testCreateDerivedUnit(): void
    {
        $u = Unit::create('kg', 'Kilogram', 'base-id', 1000);

        self::assertSame('base-id', $u->baseUnitId);
        self::assertSame(1000, $u->factor);
        self::assertFalse($u->isBase());
    }

    /** @return list<array{string}> */
    public static function invalidCodeProvider(): array
    {
        return [
            [''],
            ['1g'],            // must start with a letter
            ['_g'],            // must start with a letter
            ['g-ram'],         // dash not allowed
            ['g ram'],         // space not allowed
            [str_repeat('a', 33)],
        ];
    }

    #[DataProvider('invalidCodeProvider')]
    public function testInvalidCodeThrows(string $code): void
    {
        try {
            Unit::create($code, 'Name', null, 1);
            self::fail('expected DomainException for code: ' . $code);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitCode, $e->errorCode);
        }
    }

    public function testCodeAtMaxLengthValid(): void
    {
        $code = 'a' . str_repeat('b', 31);
        $u = Unit::create($code, 'Name', null, 1);

        self::assertSame($code, $u->code);
    }

    public function testBlankNameThrowsInvalidName(): void
    {
        try {
            Unit::create('g', '   ', null, 1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidName, $e->errorCode);
        }
    }

    public function testZeroFactorThrowsInvalidUnitFactor(): void
    {
        try {
            Unit::create('g', 'Gram', null, 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitFactor, $e->errorCode);
        }
    }

    public function testNegativeFactorThrowsInvalidUnitFactor(): void
    {
        try {
            Unit::create('kg', 'Kilogram', 'base', -1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitFactor, $e->errorCode);
        }
    }

    public function testBaseUnitWithFactorNotOneThrows(): void
    {
        try {
            Unit::create('g', 'Gram', null, 5);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitFactor, $e->errorCode);
        }
    }

    public function testDerivedUnitWithFactorOneIsAllowed(): void
    {
        $u = Unit::create('ml', 'Millilitre', 'base', 1);

        self::assertSame(1, $u->factor);
        self::assertFalse($u->isBase());
    }
}

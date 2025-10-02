<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Domain;

use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\Warehouse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    public function testCreateNormalisesCodeAndTrimsName(): void
    {
        $w = Warehouse::create('  MAIN_01  ', '  Main Warehouse  ');

        self::assertSame('main_01', $w->code, 'code is lowercased and trimmed');
        self::assertSame('Main Warehouse', $w->name);
        self::assertSame('default', $w->tenantId);
        self::assertNotSame('', $w->id);
        self::assertFalse($w->isArchived());
        self::assertEquals($w->createdAt, $w->updatedAt);
    }

    /** @return iterable<string, array{string}> */
    public static function validCodes(): iterable
    {
        yield 'lowercase' => ['main'];
        yield 'digits' => ['wh1'];
        yield 'with-dash' => ['wh-2'];
        yield 'with-underscore' => ['wh_2'];
        yield 'single-char' => ['a'];
        yield 'single-digit' => ['0'];
    }

    #[DataProvider('validCodes')]
    public function testValidCodesAccepted(string $code): void
    {
        $w = Warehouse::create($code, 'name');
        self::assertSame($code, $w->code);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'leading-dash' => ['-wh'];
        yield 'leading-underscore' => ['_wh'];
        yield 'space-inside' => ['wh 1'];
        yield 'illegal-char' => ['wh!'];
        yield 'too-long' => [str_repeat('a', 33)];
    }

    #[DataProvider('invalidCodes')]
    public function testInvalidCodesRejected(string $code): void
    {
        try {
            Warehouse::create($code, 'name');
            self::fail('expected DomainException for code ' . var_export($code, true));
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidCode, $e->errorCode);
        }
    }

    public function testMaxLengthCodeAccepted(): void
    {
        $code = str_repeat('a', 32);
        $w = Warehouse::create($code, 'name');
        self::assertSame($code, $w->code);
    }

    public function testEmptyNameRejected(): void
    {
        try {
            Warehouse::create('main', '   ');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidName, $e->errorCode);
        }
    }

    public function testIsArchivedReflectsArchivedAt(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $archived = new Warehouse('id', 'default', 'main', 'Main', $now, $now, $now);

        self::assertTrue($archived->isArchived());
    }
}

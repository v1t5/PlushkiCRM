<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Domain;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnumsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validMovementTypes(): iterable
    {
        yield 'IN' => ['IN'];
        yield 'OUT' => ['OUT'];
        yield 'WASTE' => ['WASTE'];
        yield 'ADJUST' => ['ADJUST'];
        yield 'CONSUMED_BY_PRODUCTION' => ['CONSUMED_BY_PRODUCTION'];
        yield 'SOLD' => ['SOLD'];
    }

    #[DataProvider('validMovementTypes')]
    public function testMovementTypeParsesValidValue(string $raw): void
    {
        self::assertTrue(MovementType::isValid($raw));
        self::assertSame($raw, MovementType::from($raw)->value);
    }

    public function testMovementTypeRejectsUnknownValue(): void
    {
        self::assertFalse(MovementType::isValid('FROBNICATE'));
        self::assertNull(MovementType::tryFrom('FROBNICATE'));
    }

    public function testMovementTypeIsCaseSensitive(): void
    {
        self::assertFalse(MovementType::isValid('in'));
    }

    public function testMovementTypeHasExactlySixCases(): void
    {
        self::assertCount(6, MovementType::cases());
    }

    /** @return iterable<string, array{string}> */
    public static function validItemKinds(): iterable
    {
        yield 'ingredient' => ['ingredient'];
        yield 'product' => ['product'];
    }

    #[DataProvider('validItemKinds')]
    public function testItemKindParsesValidValue(string $raw): void
    {
        self::assertTrue(ItemKind::isValid($raw));
        self::assertSame($raw, ItemKind::from($raw)->value);
    }

    public function testItemKindRejectsUnknownValue(): void
    {
        self::assertFalse(ItemKind::isValid('Ingredient'));
        self::assertNull(ItemKind::tryFrom('widget'));
    }

    public function testItemKindHasExactlyTwoCases(): void
    {
        self::assertCount(2, ItemKind::cases());
    }
}

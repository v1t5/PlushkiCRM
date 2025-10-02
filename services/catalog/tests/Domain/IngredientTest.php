<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IngredientTest extends TestCase
{
    public function testCreateHappyPath(): void
    {
        $i = Ingredient::create('  flour-1  ', '  Wheat Flour  ', 'unit-id', 5000);

        self::assertSame('FLOUR-1', $i->sku, 'sku trimmed and upper-cased');
        self::assertSame('Wheat Flour', $i->name);
        self::assertSame('unit-id', $i->defaultUnitId);
        self::assertSame(5000, $i->lowStockThresholdQty);
        self::assertSame(Ingredient::DEFAULT_TENANT, $i->tenantId);
        self::assertFalse($i->isArchived());
    }

    public function testZeroThresholdAllowed(): void
    {
        $i = Ingredient::create('SKU', 'Name', 'unit-id', 0);

        self::assertSame(0, $i->lowStockThresholdQty);
    }

    /** @return list<array{string}> */
    public static function invalidSkuProvider(): array
    {
        return [
            [''],
            ['has space'],
            ['lower#'],
            ['-leading'],
        ];
    }

    #[DataProvider('invalidSkuProvider')]
    public function testInvalidSkuThrows(string $sku): void
    {
        try {
            Ingredient::create($sku, 'Name', 'unit-id', 0);
            self::fail('expected DomainException for sku: ' . $sku);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSKU, $e->errorCode);
        }
    }

    public function testBlankNameThrowsInvalidName(): void
    {
        try {
            Ingredient::create('SKU', '   ', 'unit-id', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidName, $e->errorCode);
        }
    }

    public function testEmptyDefaultUnitIdThrowsInvalidUnitRef(): void
    {
        try {
            Ingredient::create('SKU', 'Name', '', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitRef, $e->errorCode);
        }
    }

    public function testNegativeThresholdThrowsInvalidThreshold(): void
    {
        try {
            Ingredient::create('SKU', 'Name', 'unit-id', -1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidThreshold, $e->errorCode);
        }
    }
}

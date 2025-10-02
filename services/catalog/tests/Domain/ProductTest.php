<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testCreateHappyPathNormalisesSkuAndTrims(): void
    {
        $p = Product::create('  croissant-1  ', '  Croissant  ', '  buttery  ', 25000, null);

        self::assertSame('CROISSANT-1', $p->sku, 'sku is trimmed and upper-cased');
        self::assertSame('Croissant', $p->name);
        self::assertSame('buttery', $p->description);
        self::assertSame(25000, $p->priceKopecks);
        self::assertNull($p->categoryId);
        self::assertSame(Product::DEFAULT_TENANT, $p->tenantId);
        self::assertFalse($p->isArchived());
        self::assertSame($p->createdAt, $p->updatedAt);
    }

    public function testCreateKeepsCategoryId(): void
    {
        $p = Product::create('SKU1', 'Name', '', 100, 'cat-123');

        self::assertSame('cat-123', $p->categoryId);
        self::assertSame('', $p->description);
    }

    public function testCreateAllowsZeroPrice(): void
    {
        $p = Product::create('SKU1', 'Name', '', 0, null);

        self::assertSame(0, $p->priceKopecks);
    }

    public function testIdIsRfc4122Uuid(): void
    {
        $p = Product::create('SKU1', 'Name', '', 0, null);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $p->id,
        );
    }

    /** @return list<array{string}> */
    public static function invalidSkuProvider(): array
    {
        return [
            [''],
            ['   '],
            ['has space'],
            ['lower#'],
            ['-leading'],
            ['_leading'],
            [str_repeat('A', 65)],
        ];
    }

    #[DataProvider('invalidSkuProvider')]
    public function testInvalidSkuThrows(string $sku): void
    {
        try {
            Product::create($sku, 'Name', '', 100, null);
            self::fail('expected DomainException for sku: ' . $sku);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSKU, $e->errorCode);
        }
    }

    public function testSkuAtMaxLengthIsValid(): void
    {
        $sku = str_repeat('A', 64);
        $p = Product::create($sku, 'Name', '', 100, null);

        self::assertSame($sku, $p->sku);
    }

    public function testBlankNameThrowsInvalidName(): void
    {
        $this->expectExceptionObject(DomainException::of(ErrorCode::InvalidName));

        Product::create('SKU1', '   ', '', 100, null);
    }

    public function testNegativePriceThrowsInvalidPrice(): void
    {
        try {
            Product::create('SKU1', 'Name', '', -1, null);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidPrice, $e->errorCode);
        }
    }

    public function testIsArchivedReflectsArchivedAt(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $archived = new Product('id', 't', null, 'SKU', 'n', '', 100, $now, $now, $now);

        self::assertTrue($archived->isArchived());
    }
}

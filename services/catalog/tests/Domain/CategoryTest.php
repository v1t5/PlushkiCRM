<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testCreateHappyPath(): void
    {
        $c = Category::create('  Pastries  ', '  Sweet-Buns  ', 5);

        self::assertSame('Pastries', $c->name);
        self::assertSame('sweet-buns', $c->slug, 'slug is trimmed and lower-cased');
        self::assertSame(5, $c->sortOrder);
        self::assertSame(Category::DEFAULT_TENANT, $c->tenantId);
        self::assertFalse($c->isArchived());
    }

    public function testCreateAllowsNegativeSortOrder(): void
    {
        $c = Category::create('Name', 'slug', -3);

        self::assertSame(-3, $c->sortOrder);
    }

    public function testBlankNameThrowsInvalidName(): void
    {
        try {
            Category::create('  ', 'slug', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidName, $e->errorCode);
        }
    }

    /** @return list<array{string}> */
    public static function invalidSlugProvider(): array
    {
        return [
            [''],
            ['-leading'],
            ['trailing-'],
            ['Double--dash'],
            ['has space'],
            ['under_score'],
        ];
    }

    #[DataProvider('invalidSlugProvider')]
    public function testInvalidSlugThrows(string $slug): void
    {
        try {
            Category::create('Name', $slug, 0);
            self::fail('expected DomainException for slug: ' . $slug);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSlug, $e->errorCode);
        }
    }

    /** @return list<array{string}> */
    public static function validSlugProvider(): array
    {
        return [
            ['a'],
            ['abc'],
            ['a-b-c'],
            ['cat1'],
            ['1-2-3'],
        ];
    }

    #[DataProvider('validSlugProvider')]
    public function testValidSlugAccepted(string $slug): void
    {
        $c = Category::create('Name', $slug, 0);

        self::assertSame($slug, $c->slug);
    }
}

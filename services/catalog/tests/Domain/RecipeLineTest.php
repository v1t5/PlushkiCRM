<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\RecipeLine;
use PHPUnit\Framework\TestCase;

final class RecipeLineTest extends TestCase
{
    public function testCreateHappyPath(): void
    {
        $line = RecipeLine::create('prod-1', 'ing-1', 'unit-1', 250);

        self::assertSame('prod-1', $line->productId);
        self::assertSame('ing-1', $line->ingredientId);
        self::assertSame('unit-1', $line->unitId);
        self::assertSame(250, $line->qty);
        self::assertNotSame('', $line->id);
    }

    public function testEmptyProductIdThrowsInvalidProductRef(): void
    {
        try {
            RecipeLine::create('', 'ing-1', 'unit-1', 1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidProductRef, $e->errorCode);
        }
    }

    public function testEmptyIngredientIdThrowsInvalidIngredientRef(): void
    {
        try {
            RecipeLine::create('prod-1', '', 'unit-1', 1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidIngredientRef, $e->errorCode);
        }
    }

    public function testEmptyUnitIdThrowsInvalidUnitRef(): void
    {
        try {
            RecipeLine::create('prod-1', 'ing-1', '', 1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitRef, $e->errorCode);
        }
    }

    public function testZeroQtyThrowsInvalidQty(): void
    {
        try {
            RecipeLine::create('prod-1', 'ing-1', 'unit-1', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }

    public function testNegativeQtyThrowsInvalidQty(): void
    {
        try {
            RecipeLine::create('prod-1', 'ing-1', 'unit-1', -5);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }
}

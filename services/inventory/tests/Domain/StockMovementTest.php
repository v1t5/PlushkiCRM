<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Domain;

use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;
use Plushki\Inventory\Domain\StockMovement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StockMovementTest extends TestCase
{
    public function testCreateInPopulatesFieldsAndDefaultsTenant(): void
    {
        $m = StockMovement::create('wh-1', ItemKind::Ingredient, 'item-1', MovementType::In, 500, '  restock  ', null, null);

        self::assertSame('wh-1', $m->warehouseId);
        self::assertSame(ItemKind::Ingredient, $m->itemKind);
        self::assertSame('item-1', $m->itemId);
        self::assertSame(MovementType::In, $m->type);
        self::assertSame(500, $m->qtyInBase);
        self::assertSame('restock', $m->reason, 'reason is trimmed');
        self::assertSame('default', $m->tenantId);
        self::assertNull($m->sourceEventId);
        self::assertNotSame('', $m->id, 'id is minted');
    }

    public function testCreateUsesProvidedOccurredAtAndSourceEventId(): void
    {
        $when = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $m = StockMovement::create('wh-1', ItemKind::Product, 'p-1', MovementType::Sold, -3, 'orders', 'evt-7', $when);

        self::assertSame($when, $m->occurredAt);
        self::assertSame('evt-7', $m->sourceEventId);
    }

    public function testCreateDefaultsOccurredAtToCreatedAtWhenNull(): void
    {
        $m = StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::In, 1, 'x', null, null);

        self::assertEquals($m->createdAt, $m->occurredAt);
    }

    public function testZeroQtyIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(ErrorCode::InvalidQty->value);

        StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Adjust, 0, 'x', null, null);
    }

    public function testEmptyWarehouseIsRejected(): void
    {
        try {
            StockMovement::create('', ItemKind::Ingredient, 'i-1', MovementType::In, 1, 'x', null, null);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidWarehouseRef, $e->errorCode);
        }
    }

    public function testEmptyItemIsRejected(): void
    {
        try {
            StockMovement::create('wh-1', ItemKind::Ingredient, '', MovementType::In, 1, 'x', null, null);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidItemRef, $e->errorCode);
        }
    }

    public function testInMustBePositive(): void
    {
        try {
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::In, -5, 'x', null, null);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }

    /** @return iterable<string, array{MovementType}> */
    public static function deductionTypes(): iterable
    {
        yield 'OUT' => [MovementType::Out];
        yield 'WASTE' => [MovementType::Waste];
        yield 'CONSUMED_BY_PRODUCTION' => [MovementType::ConsumedByProduction];
        yield 'SOLD' => [MovementType::Sold];
    }

    #[DataProvider('deductionTypes')]
    public function testDeductionTypesMustBeNegative(MovementType $type): void
    {
        try {
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', $type, 5, 'x', null, null);
            self::fail('expected DomainException for positive ' . $type->value);
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }

    #[DataProvider('deductionTypes')]
    public function testDeductionTypesAcceptNegative(MovementType $type): void
    {
        $m = StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', $type, -5, 'x', null, null);
        self::assertSame(-5, $m->qtyInBase);
    }

    public function testInAcceptsPositive(): void
    {
        $m = StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::In, 5, 'x', null, null);
        self::assertSame(5, $m->qtyInBase);
    }

    public function testAdjustAcceptsBothSigns(): void
    {
        $up = StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Adjust, 7, 'x', null, null);
        $down = StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Adjust, -7, 'x', null, null);

        self::assertSame(7, $up->qtyInBase);
        self::assertSame(-7, $down->qtyInBase);
    }
}

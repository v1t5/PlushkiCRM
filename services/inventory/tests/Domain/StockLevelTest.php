<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Domain;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\MovementType;
use Plushki\Inventory\Domain\StockMovement;
use PHPUnit\Framework\TestCase;

final class StockLevelTest extends TestCase
{
    public function testConstructionHoldsIntBaseUnits(): void
    {
        $l = new StockLevel('default', 'wh-1', ItemKind::Ingredient, 'i-1', 1500);

        self::assertSame('default', $l->tenantId);
        self::assertSame('wh-1', $l->warehouseId);
        self::assertSame(ItemKind::Ingredient, $l->itemKind);
        self::assertSame('i-1', $l->itemId);
        self::assertSame(1500, $l->qtyInBase);
        self::assertNull($l->updatedAt);
    }

    /**
     * The running level is SUM(qty_in_base) over the ledger; verify the signed
     * semantics by folding a sequence of movements built via the domain factory.
     */
    public function testRunningTotalIsSumOfSignedMovements(): void
    {
        $movements = [
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::In, 1000, 'restock', null, null),
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Out, -200, 'pick', null, null),
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Waste, -50, 'spoiled', null, null),
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Adjust, 25, 'recount', null, null),
        ];

        $sum = 0;
        foreach ($movements as $m) {
            $sum += $m->qtyInBase;
        }

        $level = new StockLevel('default', 'wh-1', ItemKind::Ingredient, 'i-1', $sum);
        self::assertSame(775, $level->qtyInBase);
    }

    public function testRunningTotalCanGoNegative(): void
    {
        // The domain does not enforce a non-negative invariant on StockLevel.
        $movements = [
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::In, 100, 'in', null, null),
            StockMovement::create('wh-1', ItemKind::Ingredient, 'i-1', MovementType::Out, -150, 'oversell', null, null),
        ];
        $sum = 0;
        foreach ($movements as $m) {
            $sum += $m->qtyInBase;
        }

        self::assertSame(-50, $sum);
    }
}

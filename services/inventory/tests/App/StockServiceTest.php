<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\App;

use Plushki\Inventory\App\StockService;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Tests\Fake\InMemoryStockRepo;
use PHPUnit\Framework\TestCase;

final class StockServiceTest extends TestCase
{
    private InMemoryStockRepo $stock;
    private StockService $svc;

    protected function setUp(): void
    {
        $this->stock = new InMemoryStockRepo();
        $this->svc = new StockService($this->stock);
    }

    public function testGetReturnsSeededLevel(): void
    {
        $this->stock->seed(new StockLevel('default', 'wh-1', ItemKind::Ingredient, 'i-1', 1200));

        $lvl = $this->svc->get('wh-1', ItemKind::Ingredient, 'i-1');

        self::assertSame(1200, $lvl->qtyInBase);
    }

    public function testGetReturnsZeroLevelOnMiss(): void
    {
        $lvl = $this->svc->get('wh-1', ItemKind::Product, 'unknown');

        self::assertSame(0, $lvl->qtyInBase);
        self::assertSame('unknown', $lvl->itemId);
    }

    public function testListFiltersByWarehouseAndKind(): void
    {
        $this->stock->seed(new StockLevel('default', 'wh-1', ItemKind::Ingredient, 'i-1', 10));
        $this->stock->seed(new StockLevel('default', 'wh-1', ItemKind::Product, 'p-1', 20));
        $this->stock->seed(new StockLevel('default', 'wh-2', ItemKind::Ingredient, 'i-2', 30));

        $byWarehouse = $this->svc->list('default', 'wh-1', null);
        self::assertCount(2, $byWarehouse);

        $byKind = $this->svc->list('default', 'wh-1', ItemKind::Ingredient);
        self::assertCount(1, $byKind);
        self::assertSame('i-1', $byKind[0]->itemId);
    }

    public function testListDefaultsEmptyTenantToDefault(): void
    {
        $this->stock->seed(new StockLevel('default', 'wh-1', ItemKind::Ingredient, 'i-1', 5));

        $rows = $this->svc->list('', null, null);

        self::assertCount(1, $rows);
    }
}

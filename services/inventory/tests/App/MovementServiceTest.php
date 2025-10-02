<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\App;

use Plushki\Inventory\App\EventLine;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\App\PostMovementInput;
use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;
use Plushki\Inventory\Domain\Warehouse;
use Plushki\Inventory\Ports\IngredientProjection;
use Plushki\Inventory\Tests\Fake\InMemoryIngredientProjectionRepo;
use Plushki\Inventory\Tests\Fake\InMemoryMovementRepo;
use Plushki\Inventory\Tests\Fake\InMemoryOutboxRepo;
use Plushki\Inventory\Tests\Fake\InMemoryStockRepo;
use Plushki\Inventory\Tests\Fake\InMemoryWarehouseRepo;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class MovementServiceTest extends TestCase
{
    private const MOVEMENT_POSTED = 'inventory.v1.movement_posted';
    private const STOCK_LOW = 'inventory.v1.stock_low';
    private const WH = 'wh-main';

    private InMemoryWarehouseRepo $warehouses;
    private InMemoryStockRepo $stock;
    private InMemoryMovementRepo $movements;
    private InMemoryIngredientProjectionRepo $ingredients;
    private InMemoryOutboxRepo $outbox;
    private MovementService $svc;

    protected function setUp(): void
    {
        $this->warehouses = new InMemoryWarehouseRepo();
        $this->stock = new InMemoryStockRepo();
        $this->movements = new InMemoryMovementRepo();
        $this->ingredients = new InMemoryIngredientProjectionRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new MovementService(
            $this->movements,
            $this->warehouses,
            $this->stock,
            $this->ingredients,
            $this->outbox,
            new NullLogger(),
        );

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->warehouses->byId[self::WH] = new Warehouse(self::WH, 'default', 'main', 'Main', $now, $now);
    }

    private function projection(string $id, int $threshold): void
    {
        $this->ingredients->upsert(new IngredientProjection(
            ingredientId: $id,
            tenantId: 'default',
            sku: 'SKU-' . $id,
            name: 'Ingredient ' . $id,
            defaultUnitCode: 'g',
            defaultUnitFactor: 1,
            thresholdQtyInBase: $threshold,
            updatedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        ));
    }

    public function testPostUpdatesStockAndRecordsMovementPostedEvent(): void
    {
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-1', 0);
        $in = new PostMovementInput(self::WH, ItemKind::Ingredient, 'i-1', MovementType::In, 1000, 'restock');

        [$mv, $lvl] = $this->svc->post($in);

        self::assertSame(1000, $mv->qtyInBase);
        self::assertSame(1000, $lvl->qtyInBase, 'running total reflects the movement');
        self::assertCount(1, $this->movements->posted);
        // movement_posted is written in the same transaction (via MovementRepo).
        self::assertCount(1, $this->movements->events);
        self::assertSame(self::MOVEMENT_POSTED, $this->movements->events[0]->schema);
        // No stock_low for an increase.
        self::assertCount(0, $this->outbox->withSchema(self::STOCK_LOW));
    }

    public function testPostThrowsWhenWarehouseArchived(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->warehouses->byId['wh-arch'] = new Warehouse('wh-arch', 'default', 'arch', 'Arch', $now, $now, $now);
        $in = new PostMovementInput('wh-arch', ItemKind::Ingredient, 'i-1', MovementType::In, 10, 'x');

        try {
            $this->svc->post($in);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::WarehouseArchived, $e->errorCode);
        }
    }

    public function testPostThrowsWhenWarehouseMissing(): void
    {
        $in = new PostMovementInput('nope', ItemKind::Ingredient, 'i-1', MovementType::In, 10, 'x');

        try {
            $this->svc->post($in);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::WarehouseNotFound, $e->errorCode);
        }
    }

    public function testLowStockEmittedWhenCrossingThreshold(): void
    {
        $this->projection('i-1', 100);
        // Start at 250, deduct 200 -> 50 which is below threshold 100. Previous (250) was above.
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-1', 250);
        $in = new PostMovementInput(self::WH, ItemKind::Ingredient, 'i-1', MovementType::Out, -200, 'pick');

        $this->svc->post($in);

        $low = $this->outbox->withSchema(self::STOCK_LOW);
        self::assertCount(1, $low);
        self::assertSame('ingredient', $low[0]->aggregateType);
        self::assertSame('i-1', $low[0]->aggregateId);
        self::assertStringContainsString('"qty_in_base":50', $low[0]->payload);
        self::assertStringContainsString('"threshold_qty_in_base":100', $low[0]->payload);
    }

    public function testLowStockNotReFiredWhenAlreadyBelowThreshold(): void
    {
        $this->projection('i-1', 100);
        // Previous 80 already below threshold -> deduct 30 -> 50; must not re-fire.
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-1', 80);
        $in = new PostMovementInput(self::WH, ItemKind::Ingredient, 'i-1', MovementType::Out, -30, 'pick');

        $this->svc->post($in);

        self::assertCount(0, $this->outbox->withSchema(self::STOCK_LOW));
    }

    public function testLowStockNotEmittedWhenStaysAboveThreshold(): void
    {
        $this->projection('i-1', 100);
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-1', 500);
        $in = new PostMovementInput(self::WH, ItemKind::Ingredient, 'i-1', MovementType::Out, -100, 'pick');

        $this->svc->post($in);

        self::assertCount(0, $this->outbox->withSchema(self::STOCK_LOW));
    }

    public function testLowStockNotEmittedForProductKind(): void
    {
        // No projection for products; even crossing, low-stock is ingredient-only.
        $this->movements->seedTotal(self::WH, ItemKind::Product, 'p-1', 250);
        $in = new PostMovementInput(self::WH, ItemKind::Product, 'p-1', MovementType::Out, -240, 'pick');

        $this->svc->post($in);

        self::assertCount(0, $this->outbox->withSchema(self::STOCK_LOW));
    }

    public function testLowStockSkippedWhenNoProjectionOrThresholdZero(): void
    {
        $this->projection('i-zero', 0); // threshold <= 0 disables alerting
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-zero', 250);
        $in = new PostMovementInput(self::WH, ItemKind::Ingredient, 'i-zero', MovementType::Out, -240, 'pick');

        $this->svc->post($in);

        self::assertCount(0, $this->outbox->withSchema(self::STOCK_LOW));
    }

    public function testApplyOrderFulfillmentPostsSoldMovementsAndFlipsSign(): void
    {
        $this->movements->seedTotal(self::WH, ItemKind::Product, 'p-1', 10);
        $lines = [new EventLine(ItemKind::Product, 'p-1', 3)];

        $this->svc->applyOrderFulfillment('evt-100', self::WH, null, $lines);

        self::assertCount(1, $this->movements->posted);
        $m = $this->movements->posted[0];
        self::assertSame(MovementType::Sold, $m->type);
        self::assertSame(-3, $m->qtyInBase, 'magnitude is sign-flipped to a deduction');
        self::assertSame('evt-100', $m->sourceEventId);
        self::assertCount(1, $this->movements->events);
        self::assertSame(self::MOVEMENT_POSTED, $this->movements->events[0]->schema);
    }

    public function testApplyTaskCompletedPostsConsumedMovements(): void
    {
        $lines = [
            new EventLine(ItemKind::Ingredient, 'i-1', 200),
            new EventLine(ItemKind::Ingredient, 'i-2', 50),
        ];

        $this->svc->applyTaskCompleted('evt-200', self::WH, null, $lines);

        self::assertCount(2, $this->movements->posted);
        foreach ($this->movements->posted as $m) {
            self::assertSame(MovementType::ConsumedByProduction, $m->type);
            self::assertLessThan(0, $m->qtyInBase);
            self::assertSame('evt-200', $m->sourceEventId);
        }
    }

    public function testApplyBatchIsIdempotentOnDuplicateEvent(): void
    {
        $lines = [new EventLine(ItemKind::Product, 'p-1', 3)];

        $this->svc->applyOrderFulfillment('evt-dup', self::WH, null, $lines);
        $postedAfterFirst = \count($this->movements->posted);
        $eventsAfterFirst = \count($this->movements->events);

        // Redelivery of the same event id: repo reports alreadyApplied, no new work.
        $this->svc->applyOrderFulfillment('evt-dup', self::WH, null, $lines);

        self::assertSame($postedAfterFirst, \count($this->movements->posted), 'no duplicate movements');
        self::assertSame($eventsAfterFirst, \count($this->movements->events), 'no duplicate events');
    }

    public function testEmptyBatchIsNoOp(): void
    {
        $this->svc->applyOrderFulfillment('evt-empty', self::WH, null, []);

        self::assertCount(0, $this->movements->posted);
        self::assertCount(0, $this->movements->events);
    }

    public function testLowStockEmittedForConsumedByProductionBatch(): void
    {
        $this->projection('i-1', 100);
        $this->movements->seedTotal(self::WH, ItemKind::Ingredient, 'i-1', 250);
        $lines = [new EventLine(ItemKind::Ingredient, 'i-1', 200)];

        $this->svc->applyTaskCompleted('evt-300', self::WH, null, $lines);

        self::assertCount(1, $this->outbox->withSchema(self::STOCK_LOW));
    }
}

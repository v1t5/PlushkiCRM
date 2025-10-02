<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Adapters\Events;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Plushki\Reporting\Adapters\Events\StockLowConsumer;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Tests\Support\FakeProjectionRepo;

final class StockLowConsumerTest extends TestCase
{
    private const EVENT_ID = '0190a4f0-1111-7111-8111-111111111111';
    private const INGREDIENT_ID = '0190a4f0-3333-7333-8333-333333333333';
    private const WAREHOUSE_ID = '0190a4f0-4444-7444-8444-444444444444';

    private function envelope(array $data, string $eventId = self::EVENT_ID, string $tenant = 'acme'): Envelope
    {
        return new Envelope(
            eventId: $eventId,
            occurredAt: '2026-06-01T08:00:00+00:00',
            tenantId: $tenant,
            traceId: '',
            actor: ['type' => 'system', 'id' => 'x'],
            schema: 'inventory.v1.stock_low',
            data: $data,
        );
    }

    public function testMapsStockLowEnvelopeIntoDto(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'ingredient_id' => self::INGREDIENT_ID,
            'sku' => 'FLR-1',
            'name' => 'Flour',
            'warehouse_id' => self::WAREHOUSE_ID,
            'threshold_qty_in_base' => 1000,
            'qty_in_base' => 250,
            'default_unit_code' => 'g',
            'default_unit_factor' => 1000,
        ]));

        $this->assertNotNull($repo->stockLow);
        $this->assertSame(self::EVENT_ID, $repo->stockLow->eventId);
        $this->assertSame('acme', $repo->stockLow->tenantId);
        $this->assertSame(self::INGREDIENT_ID, $repo->stockLow->ingredientId);
        $this->assertSame('FLR-1', $repo->stockLow->sku);
        $this->assertSame('Flour', $repo->stockLow->name);
        $this->assertSame(self::WAREHOUSE_ID, $repo->stockLow->warehouseId);
        $this->assertSame(1000, $repo->stockLow->thresholdQtyInBase);
        $this->assertSame(250, $repo->stockLow->currentQtyInBase);
        $this->assertSame('g', $repo->stockLow->defaultUnitCode);
        $this->assertSame(1000, $repo->stockLow->defaultUnitFactor);
    }

    public function testInvalidWarehouseIdBecomesNull(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'ingredient_id' => self::INGREDIENT_ID,
            'warehouse_id' => 'not-a-uuid',
        ]));

        $this->assertNotNull($repo->stockLow);
        $this->assertNull($repo->stockLow->warehouseId);
    }

    public function testMissingWarehouseIdBecomesNull(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['ingredient_id' => self::INGREDIENT_ID]));

        $this->assertNotNull($repo->stockLow);
        $this->assertNull($repo->stockLow->warehouseId);
    }

    public function testDefaultUnitFactorDefaultsToOne(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['ingredient_id' => self::INGREDIENT_ID]));

        $this->assertNotNull($repo->stockLow);
        $this->assertSame(1, $repo->stockLow->defaultUnitFactor);
    }

    public function testEmptyTenantFallsBackToDefault(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['ingredient_id' => self::INGREDIENT_ID], tenant: ''));

        $this->assertNotNull($repo->stockLow);
        $this->assertSame('default', $repo->stockLow->tenantId);
    }

    public function testInvalidEventIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope(['ingredient_id' => self::INGREDIENT_ID], eventId: 'bad'));
    }

    public function testInvalidIngredientIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new StockLowConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope(['ingredient_id' => 'bad']));
    }
}

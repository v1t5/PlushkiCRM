<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Adapters\Events;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Plushki\Reporting\Adapters\Events\MovementsConsumer;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Tests\Support\FakeProjectionRepo;

final class MovementsConsumerTest extends TestCase
{
    private const EVENT_ID = '0190a4f0-1111-7111-8111-111111111111';
    private const ITEM_ID = '0190a4f0-5555-7555-8555-555555555555';

    private function envelope(array $data, string $eventId = self::EVENT_ID, string $tenant = 'acme', string $occurredAt = '2026-06-01T12:00:00+00:00'): Envelope
    {
        return new Envelope(
            eventId: $eventId,
            occurredAt: $occurredAt,
            tenantId: $tenant,
            traceId: '',
            actor: ['type' => 'system', 'id' => 'x'],
            schema: 'inventory.v1.movement_posted',
            data: $data,
        );
    }

    public function testMapsMovementEnvelopeIntoDto(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'item_kind' => 'ingredient',
            'item_id' => self::ITEM_ID,
            'item_sku' => 'FLR-1',
            'item_name' => 'Flour',
            'type' => 'WASTE',
            'qty_in_base' => -500,
        ]));

        $this->assertNotNull($repo->movement);
        $this->assertSame(self::EVENT_ID, $repo->movement->eventId);
        $this->assertSame('acme', $repo->movement->tenantId);
        $this->assertSame('ingredient', $repo->movement->itemKind);
        $this->assertSame(self::ITEM_ID, $repo->movement->itemId);
        $this->assertSame('FLR-1', $repo->movement->itemSku);
        $this->assertSame('Flour', $repo->movement->itemName);
        $this->assertSame('WASTE', $repo->movement->type);
    }

    public function testQtyIsKeptSigned(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'item_id' => self::ITEM_ID,
            'qty_in_base' => -777,
        ]));

        $this->assertNotNull($repo->movement);
        $this->assertSame(-777, $repo->movement->qtyInBase);
    }

    public function testDataOccurredAtOverridesEnvelope(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'item_id' => self::ITEM_ID,
            'occurred_at' => '2026-05-15T06:00:00+00:00',
        ], occurredAt: '2026-06-01T12:00:00+00:00'));

        $this->assertNotNull($repo->movement);
        $this->assertSame('2026-05-15', $repo->movement->day->format('Y-m-d'));
        $this->assertSame('2026-05-15', $repo->movement->occurredAt->format('Y-m-d'));
    }

    public function testFallsBackToEnvelopeOccurredAtWhenDataMissing(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'item_id' => self::ITEM_ID,
        ], occurredAt: '2026-06-01T12:00:00+00:00'));

        $this->assertNotNull($repo->movement);
        $this->assertSame('2026-06-01', $repo->movement->day->format('Y-m-d'));
    }

    public function testEmptyTenantFallsBackToDefault(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['item_id' => self::ITEM_ID], tenant: ''));

        $this->assertNotNull($repo->movement);
        $this->assertSame('default', $repo->movement->tenantId);
    }

    public function testInvalidEventIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope(['item_id' => self::ITEM_ID], eventId: 'bad'));
    }

    public function testInvalidItemIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new MovementsConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope(['item_id' => 'bad']));
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Adapters\Events;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Plushki\Reporting\Adapters\Events\OrdersConsumer;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Tests\Support\FakeProjectionRepo;

final class OrdersConsumerTest extends TestCase
{
    private const EVENT_ID = '0190a4f0-1111-7111-8111-111111111111';
    private const PRODUCT_ID = '0190a4f0-2222-7222-8222-222222222222';

    private function envelope(array $data, string $eventId = self::EVENT_ID, string $tenant = 'acme', string $occurredAt = '2026-06-01T10:30:00+00:00'): Envelope
    {
        return new Envelope(
            eventId: $eventId,
            occurredAt: $occurredAt,
            tenantId: $tenant,
            traceId: '',
            actor: ['type' => 'system', 'id' => 'x'],
            schema: 'orders.v1.fulfilled',
            data: $data,
        );
    }

    public function testMapsFulfilledEnvelopeIntoDto(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'status' => 'fulfilled',
            'channel' => 'pos',
            'total_kopecks' => 15000,
            'items' => [
                ['product_id' => self::PRODUCT_ID, 'sku' => 'BUN-1', 'name' => 'Bun', 'qty' => 3, 'price_kopecks' => 5000],
            ],
        ]));

        $this->assertNotNull($repo->fulfilled);
        $this->assertSame(self::EVENT_ID, $repo->fulfilled->eventId);
        $this->assertSame('acme', $repo->fulfilled->tenantId);
        $this->assertSame('pos', $repo->fulfilled->channel);
        $this->assertSame(15000, $repo->fulfilled->totalKopecks);
        $this->assertCount(1, $repo->fulfilled->items);

        $item = $repo->fulfilled->items[0];
        $this->assertSame(self::PRODUCT_ID, $item->productId);
        $this->assertSame('BUN-1', $item->sku);
        $this->assertSame('Bun', $item->name);
        $this->assertSame(3, $item->qty);
        $this->assertSame(5000, $item->priceKopecks);
    }

    public function testDayIsDerivedFromOccurredAt(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'status' => 'fulfilled',
        ], occurredAt: '2026-06-01T23:59:00+00:00'));

        $this->assertNotNull($repo->fulfilled);
        $this->assertSame('2026-06-01', $repo->fulfilled->day->format('Y-m-d'));
    }

    public function testEmptyTenantFallsBackToDefault(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['status' => 'fulfilled'], tenant: ''));

        $this->assertNotNull($repo->fulfilled);
        $this->assertSame('default', $repo->fulfilled->tenantId);
    }

    public function testNonFulfilledStatusIsIgnored(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['status' => 'created']));

        $this->assertNull($repo->fulfilled);
    }

    public function testMissingStatusIsIgnored(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([]));

        $this->assertNull($repo->fulfilled);
    }

    public function testInvalidEventIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope(['status' => 'fulfilled'], eventId: 'not-a-uuid'));
    }

    public function testInvalidProductIdIsPoison(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $this->expectException(PoisonException::class);
        $consumer->handle($this->envelope([
            'status' => 'fulfilled',
            'items' => [['product_id' => 'bad', 'qty' => 1]],
        ]));
    }

    public function testNonArrayItemsAreSkipped(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope([
            'status' => 'fulfilled',
            'items' => ['scalar', ['product_id' => self::PRODUCT_ID]],
        ]));

        $this->assertNotNull($repo->fulfilled);
        $this->assertCount(1, $repo->fulfilled->items);
        $this->assertSame(0, $repo->fulfilled->items[0]->qty);
    }

    public function testMissingOptionalFieldsDefaultToZeroAndEmpty(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['status' => 'fulfilled']));

        $this->assertNotNull($repo->fulfilled);
        $this->assertSame('', $repo->fulfilled->channel);
        $this->assertSame(0, $repo->fulfilled->totalKopecks);
        $this->assertSame([], $repo->fulfilled->items);
    }

    public function testEmptyOccurredAtFallsBackToNow(): void
    {
        $repo = new FakeProjectionRepo();
        $consumer = new OrdersConsumer($repo, new NullLogger());

        $consumer->handle($this->envelope(['status' => 'fulfilled'], occurredAt: ''));

        $this->assertNotNull($repo->fulfilled);
        // No exception; a valid datetime was produced from the now() fallback.
        $this->assertNotSame('', $repo->fulfilled->occurredAt->format('Y-m-d'));
    }
}

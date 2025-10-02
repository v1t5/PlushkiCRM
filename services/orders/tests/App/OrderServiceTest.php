<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\App;

use Plushki\Orders\App\OrderService;
use Plushki\Orders\App\PlaceItem;
use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Ports\CatalogProduct;
use Plushki\Orders\Tests\Fake\FakeCatalogClient;
use Plushki\Orders\Tests\Fake\FakeOrderRepo;
use Plushki\Orders\Tests\Fake\FakeOutboxRepo;
use PHPUnit\Framework\TestCase;

final class OrderServiceTest extends TestCase
{
    private FakeOrderRepo $orders;
    private FakeOutboxRepo $outbox;
    private FakeCatalogClient $catalog;
    private OrderService $svc;

    protected function setUp(): void
    {
        $this->orders = new FakeOrderRepo();
        $this->outbox = new FakeOutboxRepo($this->orders);
        $this->catalog = new FakeCatalogClient();
        $this->catalog->add(new CatalogProduct('p1', 'SKU-1', 'Croissant', 15000));
        $this->catalog->add(new CatalogProduct('p2', 'SKU-2', 'Bun', 9900));
        $this->svc = new OrderService($this->orders, $this->outbox, $this->catalog);
    }

    public function testPlaceHappyPath(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust-1', [
            new PlaceItem('p1', 3),
            new PlaceItem('p2', 2),
        ]);

        self::assertSame(Status::Placed, $order->status);
        self::assertSame(Channel::TG, $order->channel);
        self::assertSame('cust-1', $order->customerRef);
        // Snapshots from catalog.
        self::assertSame('Croissant', $order->items[0]->nameSnapshot);
        self::assertSame('SKU-1', $order->items[0]->skuSnapshot);
        self::assertSame(15000, $order->items[0]->priceKopecksSnapshot);
        // Lines numbered 1..N.
        self::assertSame([1, 2], array_map(static fn ($i) => $i->lineNo, $order->items));
        // Total = 3*15000 + 2*9900 = 64800.
        self::assertSame(64800, $order->totalKopecks);
        // Persisted and retrievable.
        self::assertSame($order->id, $this->svc->get($order->id)->id);
    }

    public function testPlacePublishesPlacedEvent(): void
    {
        $order = $this->svc->place(Channel::POS, 'cust-2', [new PlaceItem('p1', 1)]);

        self::assertCount(1, $this->outbox->events);
        $evt = $this->outbox->lastEvent();
        self::assertSame('orders.v1.placed', $evt->schema);
        self::assertSame($order->id, $evt->aggregateId);
        self::assertSame('order', $evt->aggregateType);
        self::assertSame('default', $evt->tenantId);

        $payload = json_decode($evt->payload, true);
        self::assertSame('orders.v1.placed', $payload['schema']);
        self::assertSame($order->id, $payload['data']['order_id']);
        self::assertSame('pos', $payload['data']['channel']);
        self::assertSame(15000, $payload['data']['total_kopecks']);
        self::assertCount(1, $payload['data']['items']);
    }

    public function testPlaceRejectsEmptyOrder(): void
    {
        try {
            $this->svc->place(Channel::TG, 'cust', []);
            self::fail('expected EmptyOrder');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::EmptyOrder, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testPlaceRejectsInvalidQuantity(): void
    {
        try {
            $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 0)]);
            self::fail('expected InvalidQuantity');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQuantity, $e->errorCode);
        }
    }

    public function testPlaceRejectsUnknownProduct(): void
    {
        try {
            $this->svc->place(Channel::TG, 'cust', [new PlaceItem('nope', 1)]);
            self::fail('expected ProductNotFound');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::ProductNotFound, $e->errorCode);
        }
    }

    public function testPlaceResolvesEveryProductViaCatalog(): void
    {
        $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1), new PlaceItem('p2', 1)]);
        self::assertSame(['p1', 'p2'], $this->catalog->requested);
    }

    public function testConfirmTransitionsAndPublishes(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1)]);

        $confirmed = $this->svc->confirm($order->id);

        self::assertSame(Status::Confirmed, $confirmed->status);
        self::assertSame(Status::Confirmed, $this->svc->get($order->id)->status);
        self::assertSame('orders.v1.confirmed', $this->outbox->lastEvent()->schema);
        self::assertSame(Status::Confirmed, $this->outbox->statusChanges[0]['status']);
    }

    public function testCancelTransitions(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1)]);
        $cancelled = $this->svc->cancel($order->id);
        self::assertSame(Status::Cancelled, $cancelled->status);
        self::assertSame('orders.v1.cancelled', $this->outbox->lastEvent()->schema);
    }

    public function testFulfillRequiresConfirmedFirst(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1)]);
        $this->svc->confirm($order->id);
        $fulfilled = $this->svc->fulfill($order->id);
        self::assertSame(Status::Fulfilled, $fulfilled->status);
        self::assertSame('orders.v1.fulfilled', $this->outbox->lastEvent()->schema);
    }

    public function testFulfillFromPlacedIsRejected(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1)]);
        try {
            $this->svc->fulfill($order->id); // placed -> fulfilled illegal
            self::fail('expected InvalidTransition');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTransition, $e->errorCode);
        }
        // No status-change event emitted on rejection.
        self::assertCount(1, $this->outbox->events); // only the placed event
    }

    public function testTransitionFromTerminalStateIsRejected(): void
    {
        $order = $this->svc->place(Channel::TG, 'cust', [new PlaceItem('p1', 1)]);
        $this->svc->cancel($order->id);
        try {
            $this->svc->confirm($order->id);
            self::fail('expected InvalidTransition');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTransition, $e->errorCode);
        }
    }

    public function testTransitionOnMissingOrderThrowsOrderNotFound(): void
    {
        try {
            $this->svc->confirm('missing-id');
            self::fail('expected OrderNotFound');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::OrderNotFound, $e->errorCode);
        }
    }

    public function testGetMissingOrderThrows(): void
    {
        try {
            $this->svc->get('nope');
            self::fail('expected OrderNotFound');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::OrderNotFound, $e->errorCode);
        }
    }
}

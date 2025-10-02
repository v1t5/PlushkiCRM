<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Domain;

use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Item;
use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\PlaceInput;
use Plushki\Orders\Domain\Status;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    /** @param list<Item> $items */
    private static function input(array $items, Channel $channel = Channel::TG, string $ref = '  cust-1 '): PlaceInput
    {
        return new PlaceInput($channel, $ref, $items);
    }

    private static function item(int $price, int $qty): Item
    {
        // lineNo intentionally 0 — create() must renumber.
        return new Item(0, 'prod-' . $price, 'Name', 'SKU', $price, $qty);
    }

    public function testCreateRejectsEmptyOrder(): void
    {
        try {
            Order::create(self::input([]));
            self::fail('expected EmptyOrder');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::EmptyOrder, $e->errorCode);
        }
    }

    public function testCreateRejectsZeroQuantity(): void
    {
        try {
            Order::create(self::input([self::item(100, 0)]));
            self::fail('expected InvalidQuantity');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQuantity, $e->errorCode);
        }
    }

    public function testCreateRejectsNegativeQuantity(): void
    {
        try {
            Order::create(self::input([self::item(100, -2)]));
            self::fail('expected InvalidQuantity');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQuantity, $e->errorCode);
        }
    }

    public function testCreateStartsInPlacedStatus(): void
    {
        $o = Order::create(self::input([self::item(500, 2)]));
        self::assertSame(Status::Placed, $o->status);
    }

    public function testCreateAssignsDefaultTenant(): void
    {
        $o = Order::create(self::input([self::item(500, 2)]));
        self::assertSame(Order::DEFAULT_TENANT, $o->tenantId);
        self::assertSame('default', $o->tenantId);
    }

    public function testCreateTrimsCustomerRef(): void
    {
        $o = Order::create(self::input([self::item(500, 1)], Channel::POS, '  cust-9  '));
        self::assertSame('cust-9', $o->customerRef);
    }

    public function testCreatePreservesChannel(): void
    {
        $o = Order::create(self::input([self::item(500, 1)], Channel::Web));
        self::assertSame(Channel::Web, $o->channel);
    }

    public function testCreateNumbersLinesOneToN(): void
    {
        $o = Order::create(self::input([
            self::item(100, 1),
            self::item(200, 1),
            self::item(300, 1),
        ]));
        $nos = array_map(static fn (Item $i): int => $i->lineNo, $o->items);
        self::assertSame([1, 2, 3], $nos);
    }

    public function testCreatePreservesInputOrder(): void
    {
        $o = Order::create(self::input([
            self::item(700, 1),
            self::item(100, 1),
        ]));
        self::assertSame(700, $o->items[0]->priceKopecksSnapshot);
        self::assertSame(100, $o->items[1]->priceKopecksSnapshot);
    }

    public function testTotalIsSumOfSubtotalsInKopecks(): void
    {
        $o = Order::create(self::input([
            self::item(15000, 3), // 45000
            self::item(9900, 2),  // 19800
        ]));
        self::assertSame(64800, $o->totalKopecks);
        self::assertIsInt($o->totalKopecks);
    }

    public function testCreateGeneratesId(): void
    {
        $o = Order::create(self::input([self::item(100, 1)]));
        self::assertNotSame('', $o->id);
    }

    public function testTransitionPlacedToConfirmed(): void
    {
        $o = Order::create(self::input([self::item(100, 1)]));
        $before = $o->updatedAt;
        $o->transition(Status::Confirmed);
        self::assertSame(Status::Confirmed, $o->status);
        self::assertGreaterThanOrEqual($before, $o->updatedAt);
    }

    public function testTransitionConfirmedToFulfilled(): void
    {
        $o = Order::create(self::input([self::item(100, 1)]));
        $o->transition(Status::Confirmed);
        $o->transition(Status::Fulfilled);
        self::assertSame(Status::Fulfilled, $o->status);
    }

    public function testTransitionRejectsIllegalMove(): void
    {
        $o = Order::create(self::input([self::item(100, 1)]));
        try {
            $o->transition(Status::Fulfilled); // placed -> fulfilled not allowed
            self::fail('expected InvalidTransition');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTransition, $e->errorCode);
        }
        // Status unchanged after a rejected transition.
        self::assertSame(Status::Placed, $o->status);
    }

    public function testTransitionRejectsFromTerminalState(): void
    {
        $o = Order::create(self::input([self::item(100, 1)]));
        $o->transition(Status::Cancelled);
        try {
            $o->transition(Status::Confirmed);
            self::fail('expected InvalidTransition');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTransition, $e->errorCode);
        }
    }
}

<?php

declare(strict_types=1);

namespace Plushki\TgBot\Tests\Adapters\Orders;

use Plushki\TgBot\Adapters\Orders\Order;
use PHPUnit\Framework\TestCase;

/**
 * Pure mapping of an orders-service order payload to the Order DTO.
 */
final class OrderParsingTest extends TestCase
{
    public function testFromArrayMapsAllFields(): void
    {
        $o = Order::fromArray([
            'id' => '0190-aaa',
            'status' => 'placed',
            'channel' => 'tg',
            'customer_ref' => 'tg:555',
            'total_kopecks' => 12345,
        ]);

        self::assertSame('0190-aaa', $o->id);
        self::assertSame('placed', $o->status);
        self::assertSame('tg', $o->channel);
        self::assertSame('tg:555', $o->customerRef);
        self::assertSame(12345, $o->totalKopecks);
    }

    public function testFromArrayDefaultsForMissingFields(): void
    {
        $o = Order::fromArray([]);

        self::assertSame('', $o->id);
        self::assertSame('', $o->status);
        self::assertSame('', $o->channel);
        self::assertSame('', $o->customerRef);
        self::assertSame(0, $o->totalKopecks);
    }

    public function testFromArrayCoercesTotalToInt(): void
    {
        $o = Order::fromArray(['total_kopecks' => '900']);

        self::assertSame(900, $o->totalKopecks);
    }
}

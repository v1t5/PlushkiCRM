<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Notification;
use Plushki\Notifications\Domain\Recipient;

final class NotificationTest extends TestCase
{
    public function testConstructionExposesAllFields(): void
    {
        $rec = new Recipient(Channel::TG, '42');
        $n = new Notification(
            eventId: 'evt-1',
            schema: 'orders.v1.placed',
            subject: 'orders.v1.placed.default',
            recipient: $rec,
            body: 'hello',
        );

        self::assertSame('evt-1', $n->eventId);
        self::assertSame('orders.v1.placed', $n->schema);
        self::assertSame('orders.v1.placed.default', $n->subject);
        self::assertSame($rec, $n->recipient);
        self::assertSame('hello', $n->body);
        self::assertSame(Channel::TG, $n->recipient->channel);
        self::assertSame('42', $n->recipient->id);
    }
}

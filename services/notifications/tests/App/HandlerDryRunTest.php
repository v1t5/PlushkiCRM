<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\App\Handler;
use Plushki\Notifications\App\OrderEvent;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Tests\Fake\DryRunSender;
use Plushki\Notifications\Tests\Fake\FakeDeliveryRepo;
use Psr\Log\NullLogger;

/**
 * DRY-RUN mode: with an empty Telegram bot token the adapter records the
 * would-be send and reports success without any I/O. Here the DryRunSender
 * stands in for that adapter so we can assert the intended message is captured
 * and the delivery is still reserved + acked.
 */
final class HandlerDryRunTest extends TestCase
{
    private function order(): OrderEvent
    {
        return new OrderEvent(
            eventId: 'evt-dry',
            schema: 'orders.v1.placed',
            subject: 'orders.v1.placed.default',
            orderId: '0190abcd1234',
            status: 'placed',
            customerRef: 'tg:42',
            channel: 'tg',
            items: [],
            total: 5000,
        );
    }

    public function testDryRunRecordsIntendedSendWithoutCallingOut(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new DryRunSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Ack, $outcome);
        self::assertCount(1, $sender->intended);
        self::assertSame('42', $sender->intended[0]['chat_id']);
        self::assertSame('orders.v1.placed', $sender->intended[0]['schema']);
        self::assertStringContainsString('50.00 ₽', $sender->intended[0]['body']);
    }

    public function testDryRunStillReservesDeliveryRow(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new DryRunSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order());

        self::assertArrayHasKey('evt-dry', $repo->rows);
        self::assertSame([], $repo->deleteCalls);
    }

    public function testDryRunDedupSkipsSecondSend(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new DryRunSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order());
        $handler->handle($this->order());

        self::assertCount(1, $sender->intended);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\App\Handler;
use Plushki\Notifications\App\OrderEvent;
use Plushki\Notifications\App\OrderEventItem;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\DomainException;
use Plushki\Notifications\Domain\ErrorCode;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Tests\Fake\FakeDeliveryRepo;
use Plushki\Notifications\Tests\Fake\FakeSender;
use Psr\Log\NullLogger;

final class HandlerOrderEventTest extends TestCase
{
    private function order(
        string $schema = 'orders.v1.placed',
        string $customerRef = 'tg:42',
        string $eventId = 'evt-1',
        array $items = [],
        int $total = 12345,
        string $orderId = '0190abcd1234',
        string $status = 'placed',
    ): OrderEvent {
        return new OrderEvent(
            eventId: $eventId,
            schema: $schema,
            subject: $schema . '.default',
            orderId: $orderId,
            status: $status,
            customerRef: $customerRef,
            channel: 'tg',
            items: $items,
            total: $total,
        );
    }

    public function testHandleSendsViaSenderAndAcks(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order(items: [new OrderEventItem('Croissant', 2)]));

        self::assertSame(Outcome::Ack, $outcome);
        self::assertCount(1, $sender->sent);
        self::assertSame('evt-1', $sender->sent[0]->eventId);
        self::assertSame(Channel::TG, $sender->sent[0]->recipient->channel);
        self::assertSame('42', $sender->sent[0]->recipient->id);
        self::assertSame(['evt-1'], $repo->reserveCalls);
        self::assertArrayHasKey('evt-1', $repo->rows);
        self::assertSame([], $repo->deleteCalls);
    }

    public function testRenderedPlacedBodyIncludesTotalAndItems(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order(items: [new OrderEventItem('Bun', 3)], total: 12345));

        $body = $sender->sent[0]->body;
        // 12345 kopecks -> "123.45 ₽"
        self::assertStringContainsString('123.45 ₽', $body);
        self::assertStringContainsString('Bun × 3', $body);
        self::assertStringContainsString('Order placed', $body);
    }

    public function testRenderedConfirmedBodyUsesShortOrderId(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order(schema: 'orders.v1.confirmed', orderId: 'abcdef1234567890'));

        // shortID() keeps the last 8 chars.
        self::assertSame('Order #34567890 is confirmed and being prepared.', $sender->sent[0]->body);
    }

    public function testUnknownSchemaFallsBackToStatusLine(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order(schema: 'orders.v1.weird', orderId: 'short', status: 'queued'));

        self::assertSame('Order #short: queued', $sender->sent[0]->body);
    }

    public function testMalformedEventTerminatesWithoutSend(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order(eventId: ''));

        self::assertSame(Outcome::Term, $outcome);
        self::assertSame([], $sender->sent);
        self::assertSame([], $repo->reserveCalls);
    }

    public function testInvalidRecipientTerminatesWithoutSend(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order(customerRef: 'garbage'));

        self::assertSame(Outcome::Term, $outcome);
        self::assertSame([], $sender->sent);
    }

    public function testUnsupportedRecipientChannelTerminates(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order(customerRef: 'sms:42'));

        self::assertSame(Outcome::Term, $outcome);
        self::assertSame([], $sender->sent);
    }

    public function testNoSenderForChannelTerminates(): void
    {
        // Handler built with zero senders -> dispatch finds none for 'tg'.
        $repo = new FakeDeliveryRepo();
        $handler = new Handler($repo, [], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Term, $outcome);
        // tryReserve must not be reached when no sender exists.
        self::assertSame([], $repo->reserveCalls);
    }

    public function testDuplicateDeliveryIsAckedAndNotSentTwice(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $first = $handler->handle($this->order());
        $second = $handler->handle($this->order());

        self::assertSame(Outcome::Ack, $first);
        self::assertSame(Outcome::Ack, $second);
        // Reserve attempted twice, but the second is a PK conflict so no second send.
        self::assertSame(['evt-1', 'evt-1'], $repo->reserveCalls);
        self::assertCount(1, $sender->sent);
        self::assertSame([], $repo->deleteCalls);
    }

    public function testReserveFailureNaksAndDoesNotSend(): void
    {
        $repo = new FakeDeliveryRepo();
        $repo->reserveThrows = new \RuntimeException('db down');
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Nak, $outcome);
        self::assertSame([], $sender->sent);
    }

    public function testSendFailureRollsBackReservationAndNaks(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender(throws: new DomainException(ErrorCode::SendFailed, 'telegram timeout'));
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Nak, $outcome);
        // Reservation rolled back so the redelivery isn't masked as a duplicate.
        self::assertSame(['evt-1'], $repo->deleteCalls);
        self::assertArrayNotHasKey('evt-1', $repo->rows);
    }

    public function testSendFailureFollowedByRetrySucceeds(): void
    {
        // First send fails (rollback), second send (fresh sender) succeeds.
        $repo = new FakeDeliveryRepo();
        $failing = new FakeSender(throws: new DomainException(ErrorCode::SendFailed, 'boom'));
        $h1 = new Handler($repo, [$failing], new NullLogger());
        self::assertSame(Outcome::Nak, $h1->handle($this->order()));
        self::assertArrayNotHasKey('evt-1', $repo->rows);

        $ok = new FakeSender();
        $h2 = new Handler($repo, [$ok], new NullLogger());
        self::assertSame(Outcome::Ack, $h2->handle($this->order()));
        self::assertCount(1, $ok->sent);
        self::assertArrayHasKey('evt-1', $repo->rows);
    }

    public function testNonRetryableSendErrorStillNaksAndRollsBack(): void
    {
        // A plain DomainException without SendFailed (e.g. InvalidRecipient from tg 403).
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender(throws: new DomainException(ErrorCode::InvalidRecipient, 'telegram 403'));
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Nak, $outcome);
        self::assertSame(['evt-1'], $repo->deleteCalls);
    }

    public function testRollbackFailureStillNaks(): void
    {
        $repo = new FakeDeliveryRepo();
        $repo->deleteThrows = new \RuntimeException('rollback exploded');
        $sender = new FakeSender(throws: new DomainException(ErrorCode::SendFailed, 'boom'));
        $handler = new Handler($repo, [$sender], new NullLogger());

        $outcome = $handler->handle($this->order());

        self::assertSame(Outcome::Nak, $outcome);
        self::assertSame(['evt-1'], $repo->deleteCalls);
    }

    public function testPlacedWithNoItemsOmitsItemsBlock(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());

        $handler->handle($this->order(items: []));

        self::assertStringNotContainsString('Items:', $sender->sent[0]->body);
    }
}

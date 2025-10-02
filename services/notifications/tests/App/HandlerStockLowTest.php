<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\App\Handler;
use Plushki\Notifications\App\StockLowEvent;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Outcome;
use Plushki\Notifications\Domain\Recipient;
use Plushki\Notifications\Tests\Fake\FakeDeliveryRepo;
use Plushki\Notifications\Tests\Fake\FakeSender;
use Psr\Log\NullLogger;

final class HandlerStockLowTest extends TestCase
{
    private function stockLow(
        string $eventId = 'sl-1',
        string $schema = 'inventory.v1.stock_low',
        string $sku = 'FLR-001',
        string $name = 'Flour',
        int $qtyInBase = 4500,
        int $thresholdQtyInBase = 10000,
        string $unitCode = 'kg',
        int $factor = 1000,
    ): StockLowEvent {
        return new StockLowEvent(
            eventId: $eventId,
            schema: $schema,
            subject: $schema . '.default',
            ingredientId: 'ing-1',
            sku: $sku,
            name: $name,
            warehouseId: 'wh-1',
            qtyInBase: $qtyInBase,
            thresholdQtyInBase: $thresholdQtyInBase,
            defaultUnitCode: $unitCode,
            defaultUnitFactor: $factor,
        );
    }

    public function testStockLowSendsToAdminAndAcks(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $outcome = $handler->handleStockLow($this->stockLow(), $admin);

        self::assertSame(Outcome::Ack, $outcome);
        self::assertCount(1, $sender->sent);
        self::assertSame('777', $sender->sent[0]->recipient->id);
    }

    public function testStockLowBodyScalesBaseUnitsToDisplayUnit(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $handler->handleStockLow($this->stockLow(qtyInBase: 4500, thresholdQtyInBase: 10000, factor: 1000), $admin);

        $body = $sender->sent[0]->body;
        // 4500 / 1000 -> "4.5 kg"; 10000 / 1000 -> "10 kg" (exact division).
        self::assertStringContainsString('Current: 4.5 kg', $body);
        self::assertStringContainsString('Threshold: 10 kg', $body);
        self::assertStringContainsString('Flour', $body);
        self::assertStringContainsString('FLR-001', $body);
    }

    public function testStockLowFallsBackToSkuWhenNameEmpty(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $handler->handleStockLow($this->stockLow(name: ''), $admin);

        self::assertStringContainsString('FLR-001 (FLR-001)', $sender->sent[0]->body);
    }

    public function testStockLowZeroFactorTreatedAsOne(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $handler->handleStockLow($this->stockLow(qtyInBase: 5, thresholdQtyInBase: 9, unitCode: 'pcs', factor: 0), $admin);

        $body = $sender->sent[0]->body;
        self::assertStringContainsString('Current: 5 pcs', $body);
        self::assertStringContainsString('Threshold: 9 pcs', $body);
    }

    public function testStockLowSkippedAndAckedWhenAdminNotConfigured(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $emptyAdmin = new Recipient(Channel::TG, '');

        $outcome = $handler->handleStockLow($this->stockLow(), $emptyAdmin);

        self::assertSame(Outcome::Ack, $outcome);
        self::assertSame([], $sender->sent);
        self::assertSame([], $repo->reserveCalls);
    }

    public function testMalformedStockLowTerminates(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $outcome = $handler->handleStockLow($this->stockLow(eventId: ''), $admin);

        self::assertSame(Outcome::Term, $outcome);
        self::assertSame([], $sender->sent);
    }

    public function testStockLowDedupSkipsSecondDelivery(): void
    {
        $repo = new FakeDeliveryRepo();
        $sender = new FakeSender();
        $handler = new Handler($repo, [$sender], new NullLogger());
        $admin = new Recipient(Channel::TG, '777');

        $first = $handler->handleStockLow($this->stockLow(), $admin);
        $second = $handler->handleStockLow($this->stockLow(), $admin);

        self::assertSame(Outcome::Ack, $first);
        self::assertSame(Outcome::Ack, $second);
        self::assertCount(1, $sender->sent);
    }
}

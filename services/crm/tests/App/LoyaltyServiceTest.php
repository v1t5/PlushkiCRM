<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\App;

use Plushki\Crm\App\CustomerService;
use Plushki\Crm\App\FulfilledInput;
use Plushki\Crm\App\LoyaltyService;
use Plushki\Crm\App\RegisterIdentity;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Tests\Fake\FakeCustomerRepo;
use Plushki\Crm\Tests\Fake\FakeLoyaltyRepo;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class LoyaltyServiceTest extends TestCase
{
    private FakeCustomerRepo $customerRepo;
    private FakeLoyaltyRepo $loyaltyRepo;
    private CustomerService $customers;
    private LoyaltyService $svc;

    protected function setUp(): void
    {
        $this->customerRepo = new FakeCustomerRepo();
        $this->loyaltyRepo = new FakeLoyaltyRepo();
        $this->customers = new CustomerService($this->customerRepo);
        $this->svc = new LoyaltyService($this->customers, $this->loyaltyRepo, new NullLogger());
    }

    private function input(string $ref, int $kopecks, string $eventId, string $orderId): FulfilledInput
    {
        return new FulfilledInput(
            eventId: $eventId,
            orderId: $orderId,
            tenantId: 'acme',
            customerRef: $ref,
            totalKopecks: $kopecks,
            occurredAt: new \DateTimeImmutable('2026-04-01T09:00:00+00:00'),
        );
    }

    public function testApplyBumpsLoyaltyForRegisteredIdentity(): void
    {
        [$customer] = $this->customers->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        $fresh = $this->svc->applyOrderFulfilled($this->input('tg:42', 50000, 'evt-1', 'ord-1'));

        self::assertTrue($fresh);
        $loyalty = $this->loyaltyRepo->get($customer->id);
        self::assertSame(1, $loyalty->visitCount);
        self::assertSame(50000, $loyalty->totalKopecks);
    }

    public function testApplyIsIdempotentPerEventId(): void
    {
        [$customer] = $this->customers->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        $first = $this->svc->applyOrderFulfilled($this->input('tg:42', 50000, 'evt-1', 'ord-1'));
        $second = $this->svc->applyOrderFulfilled($this->input('tg:42', 50000, 'evt-1', 'ord-1'));

        self::assertTrue($first);
        self::assertFalse($second);

        $loyalty = $this->loyaltyRepo->get($customer->id);
        self::assertSame(1, $loyalty->visitCount);
        self::assertSame(50000, $loyalty->totalKopecks);
        // Exactly one loyalty_updated event emitted across the redelivery.
        self::assertCount(1, $this->loyaltyRepo->events);
    }

    public function testTwoDistinctOrdersAccumulate(): void
    {
        [$customer] = $this->customers->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        $this->svc->applyOrderFulfilled($this->input('tg:42', 30000, 'evt-1', 'ord-1'));
        $this->svc->applyOrderFulfilled($this->input('tg:42', 19900, 'evt-2', 'ord-2'));

        $loyalty = $this->loyaltyRepo->get($customer->id);
        self::assertSame(2, $loyalty->visitCount);
        self::assertSame(49900, $loyalty->totalKopecks);
        self::assertCount(2, $this->loyaltyRepo->events);
    }

    public function testApplyEmitsLoyaltyUpdatedEvent(): void
    {
        [$customer] = $this->customers->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        $this->svc->applyOrderFulfilled($this->input('tg:42', 50000, 'evt-1', 'ord-1'));

        self::assertCount(1, $this->loyaltyRepo->events);
        $evt = $this->loyaltyRepo->events[0];
        self::assertSame('crm.v1.loyalty_updated', $evt->schema);
        self::assertSame('loyalty', $evt->aggregateType);
        self::assertSame($customer->id, $evt->aggregateId);
        self::assertSame('acme', $evt->tenantId);

        $payload = json_decode($evt->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($customer->id, $payload['data']['customer_id']);
        self::assertSame('ord-1', $payload['data']['order_id']);
        self::assertSame('tg:42', $payload['data']['customer_ref']);
        self::assertSame(50000, $payload['data']['total_kopecks']);
    }

    public function testPosRefRollsIntoTenantWalkin(): void
    {
        $fresh = $this->svc->applyOrderFulfilled($this->input('pos:walk-in', 12000, 'evt-1', 'ord-1'));

        self::assertTrue($fresh);
        $walkin = $this->customerRepo->ensureWalkin('acme');
        $loyalty = $this->loyaltyRepo->get($walkin->id);
        self::assertSame(1, $loyalty->visitCount);
        self::assertSame(12000, $loyalty->totalKopecks);
    }

    public function testUnknownIdentityIsSkippedNoEvent(): void
    {
        // No customer registered for tg:99 → unattributable, skipped, not fresh.
        $fresh = $this->svc->applyOrderFulfilled($this->input('tg:99', 50000, 'evt-1', 'ord-1'));

        self::assertFalse($fresh);
        self::assertCount(0, $this->loyaltyRepo->events);
    }

    public function testUnparsableRefIsSkippedNoEvent(): void
    {
        $fresh = $this->svc->applyOrderFulfilled($this->input('garbage', 50000, 'evt-1', 'ord-1'));

        self::assertFalse($fresh);
        self::assertCount(0, $this->loyaltyRepo->events);
    }

    public function testGetDelegatesToRepo(): void
    {
        [$customer] = $this->customers->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);
        $this->svc->applyOrderFulfilled($this->input('tg:42', 50000, 'evt-1', 'ord-1'));

        $loyalty = $this->svc->get($customer->id);

        self::assertSame(50000, $loyalty->totalKopecks);
    }
}

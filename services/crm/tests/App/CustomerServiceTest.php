<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\App;

use Plushki\Crm\App\CustomerService;
use Plushki\Crm\App\RegisterIdentity;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Tests\Fake\FakeCustomerRepo;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    private FakeCustomerRepo $repo;
    private CustomerService $svc;

    protected function setUp(): void
    {
        $this->repo = new FakeCustomerRepo();
        $this->svc = new CustomerService($this->repo);
    }

    public function testRegisterPersistsCustomerAndIdentities(): void
    {
        [$customer, $identities] = $this->svc->register('acme', 'Jane Doe', [
            new RegisterIdentity(IdentityType::TG, '42'),
            new RegisterIdentity(IdentityType::Phone, '+7999'),
        ]);

        self::assertSame('acme', $customer->tenantId);
        self::assertSame('Jane Doe', $customer->displayName);
        self::assertNotSame('', $customer->id);
        self::assertCount(2, $identities);
        self::assertArrayHasKey($customer->id, $this->repo->customers);
        self::assertSame($customer->id, $identities[0]->customerId);
        self::assertSame(IdentityType::TG, $identities[0]->type);
        self::assertSame('42', $identities[0]->value);
    }

    public function testRegisterTrimsDisplayNameAndIdentityValues(): void
    {
        [$customer, $identities] = $this->svc->register('acme', '  Jane  ', [
            new RegisterIdentity(IdentityType::Email, '  a@b.co  '),
        ]);

        self::assertSame('Jane', $customer->displayName);
        self::assertSame('a@b.co', $identities[0]->value);
    }

    public function testRegisterDefaultsEmptyTenantToDefault(): void
    {
        [$customer] = $this->svc->register('', 'Anon', []);

        self::assertSame('default', $customer->tenantId);
    }

    public function testRegisterEmitsCustomerRegisteredEvent(): void
    {
        [$customer] = $this->svc->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        self::assertCount(1, $this->repo->events);
        $evt = $this->repo->events[0];
        self::assertSame('crm.v1.customer_registered', $evt->schema);
        self::assertSame('customer', $evt->aggregateType);
        self::assertSame($customer->id, $evt->aggregateId);
        self::assertSame('acme', $evt->tenantId);

        $payload = json_decode($evt->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('crm.v1.customer_registered', $payload['schema']);
        self::assertSame($customer->id, $payload['data']['customer_id']);
        self::assertSame('Jane', $payload['data']['display_name']);
        self::assertSame(
            [['type' => 'tg', 'value' => '42']],
            $payload['data']['identities'],
        );
        self::assertSame('system', $payload['actor']['type']);
        self::assertSame('crm', $payload['actor']['id']);
    }

    public function testRegisterRejectsBlankIdentityValue(): void
    {
        try {
            $this->svc->register('acme', 'Jane', [
                new RegisterIdentity(IdentityType::TG, '   '),
            ]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IdentityValueRequired, $e->errorCode);
        }
    }

    public function testRegisterRejectsDuplicateIdentityBoundElsewhere(): void
    {
        $this->svc->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        try {
            $this->svc->register('acme', 'Impostor', [
                new RegisterIdentity(IdentityType::TG, '42'),
            ]);
            self::fail('expected IdentityConflict');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IdentityConflict, $e->errorCode);
        }

        // Only the first registration persisted (no duplicate / partial write asserted at port level).
        self::assertCount(1, $this->repo->events);
    }

    public function testGetReturnsCustomerWithIdentities(): void
    {
        [$customer] = $this->svc->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        [$got, $ids] = $this->svc->get($customer->id);

        self::assertSame($customer->id, $got->id);
        self::assertCount(1, $ids);
        self::assertSame('42', $ids[0]->value);
    }

    public function testGetUnknownCustomerThrowsCustomerNotFound(): void
    {
        try {
            $this->svc->get('missing');
            self::fail('expected CustomerNotFound');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::CustomerNotFound, $e->errorCode);
        }
    }

    public function testResolveByIdentityFindsRegisteredCustomer(): void
    {
        [$customer] = $this->svc->register('acme', 'Jane', [
            new RegisterIdentity(IdentityType::TG, '42'),
        ]);

        [$got, $identity] = $this->svc->resolveByIdentity('acme', IdentityType::TG, '42');

        self::assertSame($customer->id, $got->id);
        self::assertSame('42', $identity->value);
    }

    public function testResolveByIdentityUnknownThrowsIdentityNotFound(): void
    {
        try {
            $this->svc->resolveByIdentity('acme', IdentityType::TG, 'nope');
            self::fail('expected IdentityNotFound');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::IdentityNotFound, $e->errorCode);
        }
    }

    public function testEnsureWalkinIsIdempotentPerTenant(): void
    {
        $a = $this->svc->ensureWalkin('acme');
        $b = $this->svc->ensureWalkin('acme');

        self::assertSame($a->id, $b->id);
        self::assertSame(2, $this->repo->ensureWalkinCalls);
    }

    public function testListFiltersByTenantAndQuery(): void
    {
        $this->svc->register('acme', 'Alice', []);
        $this->svc->register('acme', 'Bob', []);
        $this->svc->register('other', 'Alice', []);

        $matches = $this->svc->list('acme', 'Ali', 10);

        self::assertCount(1, $matches);
        self::assertSame('Alice', $matches[0]->customer->displayName);
        self::assertSame('acme', $matches[0]->customer->tenantId);
    }
}

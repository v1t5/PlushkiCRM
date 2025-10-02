<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\App;

use Plushki\Orders\App\OrderService;
use Plushki\Orders\App\PlaceItem;
use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Ports\CatalogProduct;
use Plushki\Orders\Ports\ListFilter;
use Plushki\Orders\Tests\Fake\FakeCatalogClient;
use Plushki\Orders\Tests\Fake\FakeOrderRepo;
use Plushki\Orders\Tests\Fake\FakeOutboxRepo;
use PHPUnit\Framework\TestCase;

final class OrderServiceListTest extends TestCase
{
    private OrderService $svc;

    protected function setUp(): void
    {
        $orders = new FakeOrderRepo();
        $outbox = new FakeOutboxRepo($orders);
        $catalog = new FakeCatalogClient();
        $catalog->add(new CatalogProduct('p1', 'SKU-1', 'Croissant', 15000));
        $this->svc = new OrderService($orders, $outbox, $catalog);
    }

    private function seed(string $ref, Channel $ch = Channel::TG): string
    {
        return $this->svc->place($ch, $ref, [new PlaceItem('p1', 1)])->id;
    }

    public function testListByCustomerReturnsOnlyMatching(): void
    {
        $this->seed('alice');
        $this->seed('alice');
        $this->seed('bob');

        $out = $this->svc->listByCustomer('default', 'alice', 50);
        self::assertCount(2, $out);
        foreach ($out as $o) {
            self::assertSame('alice', $o->customerRef);
        }
    }

    public function testListByCustomerEmptyTenantFallsBackToDefault(): void
    {
        $this->seed('alice');
        $out = $this->svc->listByCustomer('', 'alice', 50);
        self::assertCount(1, $out);
    }

    public function testListByCustomerClampsBadLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('alice');
        }
        // limit <= 0 becomes 50, so all 5 returned.
        $out = $this->svc->listByCustomer('default', 'alice', 0);
        self::assertCount(5, $out);
    }

    public function testListByCustomerHonoursPositiveLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('alice');
        }
        $out = $this->svc->listByCustomer('default', 'alice', 2);
        self::assertCount(2, $out);
    }

    public function testListFilterByStatus(): void
    {
        $a = $this->seed('alice');
        $this->seed('bob');
        $this->svc->confirm($a);

        $confirmed = $this->svc->list(new ListFilter(tenantId: 'default', status: Status::Confirmed));
        self::assertCount(1, $confirmed);
        self::assertSame($a, $confirmed[0]->id);
    }

    public function testListFilterByChannel(): void
    {
        $this->seed('alice', Channel::TG);
        $this->seed('bob', Channel::POS);

        $pos = $this->svc->list(new ListFilter(tenantId: 'default', channel: Channel::POS));
        self::assertCount(1, $pos);
        self::assertSame(Channel::POS, $pos[0]->channel);
    }

    public function testListFilterByCustomerRef(): void
    {
        $this->seed('alice');
        $this->seed('bob');

        $out = $this->svc->list(new ListFilter(tenantId: 'default', customerRef: 'bob'));
        self::assertCount(1, $out);
        self::assertSame('bob', $out[0]->customerRef);
    }

    public function testListNoFilterReturnsAllForTenant(): void
    {
        $this->seed('alice');
        $this->seed('bob');
        $out = $this->svc->list(new ListFilter(tenantId: 'default'));
        self::assertCount(2, $out);
    }

    public function testListIsolatesByTenant(): void
    {
        $this->seed('alice');
        $out = $this->svc->list(new ListFilter(tenantId: 'other'));
        self::assertCount(0, $out);
    }
}

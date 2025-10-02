<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\App;

use Plushki\Inventory\App\WarehouseService;
use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Tests\Fake\InMemoryWarehouseRepo;
use PHPUnit\Framework\TestCase;

final class WarehouseServiceTest extends TestCase
{
    private InMemoryWarehouseRepo $repo;
    private WarehouseService $svc;

    protected function setUp(): void
    {
        $this->repo = new InMemoryWarehouseRepo();
        $this->svc = new WarehouseService($this->repo);
    }

    public function testCreatePersistsWarehouse(): void
    {
        $w = $this->svc->create('MAIN', 'Main Warehouse');

        self::assertSame('main', $w->code);
        self::assertSame('Main Warehouse', $w->name);
        self::assertSame($w, $this->repo->getById($w->id));
    }

    public function testCreateRejectsInvalidCode(): void
    {
        try {
            $this->svc->create('-bad', 'name');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidCode, $e->errorCode);
        }
    }

    public function testGetReturnsWarehouse(): void
    {
        $w = $this->svc->create('main', 'Main');

        self::assertSame($w, $this->svc->get($w->id));
    }

    public function testGetThrowsWhenMissing(): void
    {
        try {
            $this->svc->get('nope');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::WarehouseNotFound, $e->errorCode);
        }
    }

    public function testListReturnsActiveWarehouses(): void
    {
        $this->svc->create('a', 'A');
        $this->svc->create('b', 'B');

        self::assertCount(2, $this->svc->list('default'));
    }

    public function testListDefaultsEmptyTenant(): void
    {
        $this->svc->create('a', 'A');

        self::assertCount(1, $this->svc->list(''));
    }

    public function testEnsureDefaultCreatesWhenMissing(): void
    {
        $w = $this->svc->ensureDefault('main', 'Main');

        self::assertSame('main', $w->code);
        self::assertCount(1, $this->repo->byId);
    }

    public function testEnsureDefaultReturnsExistingWithoutDuplicating(): void
    {
        $first = $this->svc->ensureDefault('main', 'Main');
        $second = $this->svc->ensureDefault('main', 'Main');

        self::assertSame($first->id, $second->id);
        self::assertCount(1, $this->repo->byId);
    }
}

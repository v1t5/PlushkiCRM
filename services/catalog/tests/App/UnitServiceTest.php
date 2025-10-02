<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\App;

use Plushki\Catalog\App\UnitService;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Tests\Fake\InMemoryOutboxRepo;
use Plushki\Catalog\Tests\Fake\InMemoryUnitRepo;
use PHPUnit\Framework\TestCase;

final class UnitServiceTest extends TestCase
{
    private InMemoryUnitRepo $units;
    private InMemoryOutboxRepo $outbox;
    private UnitService $svc;

    protected function setUp(): void
    {
        $this->units = new InMemoryUnitRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new UnitService($this->units, $this->outbox);
    }

    private function ts(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    private function baseUnit(string $id = 'g'): Unit
    {
        return new Unit($id, 'default', 'g', 'Gram', null, 1, $this->ts(), $this->ts());
    }

    public function testCreateBaseUnitEmitsEventWithoutBaseUnitId(): void
    {
        $u = $this->svc->create('g', 'Gram', null, 1);

        self::assertTrue($u->isBase());
        self::assertCount(1, $this->outbox->units);
        $evt = $this->outbox->lastEvent();
        self::assertSame('catalog.v1.unit_created', $evt->schema);
        self::assertSame('unit', $evt->aggregateType);
        self::assertStringContainsString('"factor":1', $evt->payload);
        self::assertStringNotContainsString('base_unit_id', $evt->payload);
    }

    public function testCreateDerivedUnitReferencingBaseEmitsBaseUnitId(): void
    {
        $base = $this->baseUnit('base-1');
        $this->units->add($base);

        $u = $this->svc->create('kg', 'Kilogram', 'base-1', 1000);

        self::assertSame('base-1', $u->baseUnitId);
        self::assertSame(1000, $u->factor);
        self::assertStringContainsString('"base_unit_id":"base-1"', $this->outbox->lastEvent()->payload);
    }

    public function testCreateWithMissingBaseThrowsUnitNotFound(): void
    {
        try {
            $this->svc->create('kg', 'Kilogram', 'missing', 1000);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitNotFound, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateWithArchivedBaseThrowsUnitArchived(): void
    {
        $archived = new Unit('base-1', 'default', 'g', 'Gram', null, 1, $this->ts(), $this->ts(), $this->ts());
        $this->units->add($archived);

        try {
            $this->svc->create('kg', 'Kilogram', 'base-1', 1000);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateWithNonBaseBaseThrowsBaseUnitMustBeBase(): void
    {
        // base-1 is itself a derived unit (has baseUnitId), so chaining is rejected.
        $derived = new Unit('base-1', 'default', 'kg', 'Kilogram', 'g', 1000, $this->ts(), $this->ts());
        $this->units->add($derived);

        try {
            $this->svc->create('t', 'Tonne', 'base-1', 1000000);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::BaseUnitMustBeBase, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateInvalidCodeThrowsBeforeOutbox(): void
    {
        try {
            $this->svc->create('1bad', 'Name', null, 1);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidUnitCode, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testGetMissingThrowsUnitNotFound(): void
    {
        try {
            $this->svc->get('nope');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnitNotFound, $e->errorCode);
        }
    }

    public function testListReturnsActiveUnits(): void
    {
        $this->units->add($this->baseUnit('g'));
        self::assertCount(1, $this->svc->list('default'));
        self::assertCount(1, $this->svc->list(''));
    }
}

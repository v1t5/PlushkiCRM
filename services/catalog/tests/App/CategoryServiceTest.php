<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\App;

use Plushki\Catalog\App\CategoryService;
use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Tests\Fake\InMemoryCategoryRepo;
use Plushki\Catalog\Tests\Fake\InMemoryOutboxRepo;
use PHPUnit\Framework\TestCase;

final class CategoryServiceTest extends TestCase
{
    private InMemoryCategoryRepo $repo;
    private InMemoryOutboxRepo $outbox;
    private CategoryService $svc;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCategoryRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new CategoryService($this->repo, $this->outbox);
    }

    public function testCreatePersistsAndEmitsEvent(): void
    {
        $c = $this->svc->create('Pastries', 'pastries', 1);

        self::assertSame('pastries', $c->slug);
        self::assertCount(1, $this->outbox->categories);
        self::assertSame($c, $this->outbox->categories[0]);
        self::assertCount(1, $this->outbox->events);

        $evt = $this->outbox->lastEvent();
        self::assertSame('catalog.v1.category_created', $evt->schema);
        self::assertSame('category', $evt->aggregateType);
        self::assertSame($c->id, $evt->aggregateId);
        self::assertSame('default', $evt->tenantId);
        self::assertStringContainsString('"slug":"pastries"', $evt->payload);
    }

    public function testCreateInvalidSlugThrowsAndEmitsNothing(): void
    {
        try {
            $this->svc->create('Name', 'Bad Slug', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSlug, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testListReturnsActiveForTenant(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $active = new Category('a', 'default', 'A', 'a', 0, $now, $now);
        $archived = new Category('b', 'default', 'B', 'b', 0, $now, $now, $now);
        $this->repo->add($active);
        $this->repo->add($archived);

        $list = $this->svc->list('default');

        self::assertSame([$active], $list);
    }

    public function testListEmptyTenantDefaultsToDefault(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->repo->add(new Category('a', 'default', 'A', 'a', 0, $now, $now));

        self::assertCount(1, $this->svc->list(''));
    }
}

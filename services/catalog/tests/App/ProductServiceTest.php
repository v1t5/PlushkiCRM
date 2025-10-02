<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\App;

use Plushki\Catalog\App\ProductService;
use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Tests\Fake\InMemoryCategoryRepo;
use Plushki\Catalog\Tests\Fake\InMemoryOutboxRepo;
use Plushki\Catalog\Tests\Fake\InMemoryProductRepo;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private InMemoryProductRepo $products;
    private InMemoryCategoryRepo $categories;
    private InMemoryOutboxRepo $outbox;
    private ProductService $svc;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepo();
        $this->categories = new InMemoryCategoryRepo();
        $this->outbox = new InMemoryOutboxRepo();
        $this->svc = new ProductService($this->products, $this->categories, $this->outbox);
    }

    private function ts(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    public function testCreateWithoutCategoryEmitsEvent(): void
    {
        $p = $this->svc->create('SKU1', 'Bun', 'tasty', 15000, null);

        self::assertSame('SKU1', $p->sku);
        self::assertCount(1, $this->outbox->products);
        $evt = $this->outbox->lastEvent();
        self::assertSame('catalog.v1.product_created', $evt->schema);
        self::assertSame('product', $evt->aggregateType);
        self::assertSame($p->id, $evt->aggregateId);
        self::assertStringContainsString('"price_kopecks":15000', $evt->payload);
        // No category was set, so the payload should omit category_id.
        self::assertStringNotContainsString('category_id', $evt->payload);
    }

    public function testCreateWithValidCategoryIncludesCategoryInPayload(): void
    {
        $cat = new Category('cat-1', 'default', 'C', 'c', 0, $this->ts(), $this->ts());
        $this->categories->add($cat);

        $p = $this->svc->create('SKU1', 'Bun', '', 100, 'cat-1');

        self::assertSame('cat-1', $p->categoryId);
        self::assertStringContainsString('"category_id":"cat-1"', $this->outbox->lastEvent()->payload);
    }

    public function testCreateWithMissingCategoryThrowsCategoryNotFound(): void
    {
        try {
            $this->svc->create('SKU1', 'Bun', '', 100, 'missing');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::CategoryNotFound, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateWithArchivedCategoryThrowsCategoryArchived(): void
    {
        $cat = new Category('cat-1', 'default', 'C', 'c', 0, $this->ts(), $this->ts(), $this->ts());
        $this->categories->add($cat);

        try {
            $this->svc->create('SKU1', 'Bun', '', 100, 'cat-1');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::CategoryArchived, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testCreateInvalidSkuThrowsBeforeOutbox(): void
    {
        try {
            $this->svc->create('bad sku', 'Bun', '', 100, null);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidSKU, $e->errorCode);
        }
        self::assertCount(0, $this->outbox->events);
    }

    public function testGetDelegatesToRepo(): void
    {
        $p = new Product('p1', 'default', null, 'SKU', 'n', '', 100, $this->ts(), $this->ts());
        $this->products->add($p);

        self::assertSame($p, $this->svc->get('p1'));
    }

    public function testGetMissingThrowsProductNotFound(): void
    {
        try {
            $this->svc->get('nope');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::ProductNotFound, $e->errorCode);
        }
    }

    public function testListFiltersByCategory(): void
    {
        $a = new Product('a', 'default', 'cat-1', 'A', 'n', '', 1, $this->ts(), $this->ts());
        $b = new Product('b', 'default', 'cat-2', 'B', 'n', '', 1, $this->ts(), $this->ts());
        $this->products->add($a);
        $this->products->add($b);

        self::assertSame([$a], $this->svc->list('default', 'cat-1'));
        self::assertCount(2, $this->svc->list('default', null));
    }
}

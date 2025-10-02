<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Platform\Events\Envelope;
use Plushki\Catalog\Ports\CategoryRepo;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;
use Plushki\Catalog\Ports\ProductRepo;

/** Orchestrates product use cases. */
final class ProductService
{
    public function __construct(
        private readonly ProductRepo $products,
        private readonly CategoryRepo $categories,
        private readonly OutboxRepo $outbox,
    ) {
    }

    public function create(string $sku, string $name, string $description, int $priceKopecks, ?string $categoryId): Product
    {
        if ($categoryId !== null) {
            $c = $this->categories->getById($categoryId);
            if ($c->isArchived()) {
                throw DomainException::of(ErrorCode::CategoryArchived);
            }
        }
        $p = Product::create($sku, $name, $description, $priceKopecks, $categoryId);
        $this->outbox->insertWithProduct($p, $this->productCreatedEvent($p));

        return $p;
    }

    public function get(string $id): Product
    {
        return $this->products->getById($id);
    }

    /** @return list<Product> */
    public function list(string $tenantId, ?string $categoryId): array
    {
        return $this->products->listActive($tenantId !== '' ? $tenantId : 'default', $categoryId);
    }

    private function productCreatedEvent(Product $p): OutboxEvent
    {
        $schema = 'catalog.v1.product_created';
        $data = [
            'product_id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'description' => $p->description,
            'price_kopecks' => $p->priceKopecks,
        ];
        if ($p->categoryId !== null) {
            $data['category_id'] = $p->categoryId;
        }
        $env = Envelope::build(
            schema: $schema,
            data: $data,
            actorType: 'system',
            actorId: 'catalog',
            occurredAt: $p->createdAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $p->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $p->id,
            aggregateType: 'product',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $p->createdAt,
            tenantId: $p->tenantId,
            traceId: $env->traceId,
        );
    }
}

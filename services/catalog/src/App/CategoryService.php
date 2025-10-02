<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Platform\Events\Envelope;
use Plushki\Catalog\Ports\CategoryRepo;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;

/**
 * Orchestrates category use cases. State changes that publish events go through
 * the outbox port (never a direct broker publish) so a partial commit cannot
 * drop the event.
 */
final class CategoryService
{
    public function __construct(
        private readonly CategoryRepo $categories,
        private readonly OutboxRepo $outbox,
    ) {
    }

    public function create(string $name, string $slug, int $sortOrder): Category
    {
        $c = Category::create($name, $slug, $sortOrder);
        $this->outbox->insertWithCategory($c, $this->categoryCreatedEvent($c));

        return $c;
    }

    /** @return list<Category> */
    public function list(string $tenantId): array
    {
        return $this->categories->listActive($tenantId !== '' ? $tenantId : 'default');
    }

    private function categoryCreatedEvent(Category $c): OutboxEvent
    {
        $schema = 'catalog.v1.category_created';
        $env = Envelope::build(
            schema: $schema,
            data: [
                'category_id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'sort_order' => $c->sortOrder,
            ],
            actorType: 'system',
            actorId: 'catalog',
            occurredAt: $c->createdAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $c->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $c->id,
            aggregateType: 'category',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $c->createdAt,
            tenantId: $c->tenantId,
            traceId: $env->traceId,
        );
    }
}

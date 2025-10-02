<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\OutboxRow;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;

/**
 * Array-backed OutboxRepo. Records each persisted aggregate alongside the event
 * it was committed with, so tests can assert the outbox received exactly one event.
 */
final class InMemoryOutboxRepo implements OutboxRepo
{
    /** @var list<OutboxEvent> */
    public array $events = [];

    /** @var list<Category> */
    public array $categories = [];

    /** @var list<Product> */
    public array $products = [];

    /** @var list<Unit> */
    public array $units = [];

    /** @var list<Ingredient> */
    public array $ingredients = [];

    /** @var array<string, list<RecipeLine>> */
    public array $recipes = [];

    public function insertWithCategory(Category $c, OutboxEvent $evt): void
    {
        $this->categories[] = $c;
        $this->events[] = $evt;
    }

    public function insertWithProduct(Product $p, OutboxEvent $evt): void
    {
        $this->products[] = $p;
        $this->events[] = $evt;
    }

    public function insertWithUnit(Unit $u, OutboxEvent $evt): void
    {
        $this->units[] = $u;
        $this->events[] = $evt;
    }

    public function insertWithIngredient(Ingredient $i, OutboxEvent $evt): void
    {
        $this->ingredients[] = $i;
        $this->events[] = $evt;
    }

    /** @param list<RecipeLine> $lines */
    public function replaceRecipe(string $productId, array $lines, OutboxEvent $evt): void
    {
        $this->recipes[$productId] = $lines;
        $this->events[] = $evt;
    }

    /** @return list<OutboxRow> */
    public function fetchUnpublished(int $limit): array
    {
        $out = [];
        foreach ($this->events as $e) {
            $out[] = new OutboxRow($e->eventId, $e->schema, $e->tenantId, $e->payload);
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param list<string> $eventIds */
    public function markPublished(array $eventIds, \DateTimeImmutable $at): void
    {
        // No-op for tests.
    }

    public function lastEvent(): OutboxEvent
    {
        return $this->events[\count($this->events) - 1];
    }
}

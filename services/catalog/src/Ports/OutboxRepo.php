<?php

declare(strict_types=1);

namespace Plushki\Catalog\Ports;

use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\OutboxStore;

/**
 * Writes the aggregate row(s) and the corresponding outbox row in one
 * transaction. Each aggregate gets its own insertWith* method because the SQL
 * is shaped to that aggregate. It also satisfies the generic OutboxStore
 * (fetchUnpublished / markPublished) consumed by the relay.
 */
interface OutboxRepo extends OutboxStore
{
    public function insertWithCategory(Category $c, OutboxEvent $evt): void;

    public function insertWithProduct(Product $p, OutboxEvent $evt): void;

    public function insertWithUnit(Unit $u, OutboxEvent $evt): void;

    public function insertWithIngredient(Ingredient $i, OutboxEvent $evt): void;

    /**
     * @param list<RecipeLine> $lines
     */
    public function replaceRecipe(string $productId, array $lines, OutboxEvent $evt): void;
}

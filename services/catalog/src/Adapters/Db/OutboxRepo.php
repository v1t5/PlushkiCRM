<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plushki\Catalog\Domain\Category;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Product;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\OutboxRow;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo as OutboxRepoPort;

/**
 * DBAL implementation of the outbox port. Each insertWith* writes the aggregate
 * row and the matching event in one transaction so the event cannot be issued
 * without the aggregate, and the aggregate cannot exist without the event
 * queued. fetchUnpublished/markPublished serve the relay.
 */
final class OutboxRepo implements OutboxRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insertWithCategory(Category $c, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($c, $evt): void {
            try {
                $tx->executeStatement(
                    'INSERT INTO categories (id, tenant_id, name, slug, sort_order, created_at, updated_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, :name, :slug, CAST(:sort_order AS integer),
                             CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                    [
                        'id' => $c->id,
                        'tenant_id' => $c->tenantId,
                        'name' => $c->name,
                        'slug' => $c->slug,
                        'sort_order' => $c->sortOrder,
                        'created_at' => Ts::fmt($c->createdAt),
                        'updated_at' => Ts::fmt($c->updatedAt),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                throw DomainException::of(ErrorCode::SlugAlreadyTaken);
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function insertWithProduct(Product $p, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($p, $evt): void {
            try {
                $tx->executeStatement(
                    'INSERT INTO products (id, tenant_id, category_id, sku, name, description, price_kopecks, created_at, updated_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, CAST(:category_id AS uuid), :sku, :name, :description,
                             CAST(:price AS bigint), CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                    [
                        'id' => $p->id,
                        'tenant_id' => $p->tenantId,
                        'category_id' => $p->categoryId,
                        'sku' => $p->sku,
                        'name' => $p->name,
                        'description' => $p->description,
                        'price' => $p->priceKopecks,
                        'created_at' => Ts::fmt($p->createdAt),
                        'updated_at' => Ts::fmt($p->updatedAt),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                throw DomainException::of(ErrorCode::SKUAlreadyTaken);
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function insertWithUnit(Unit $u, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($u, $evt): void {
            try {
                $tx->executeStatement(
                    'INSERT INTO units (id, tenant_id, code, name, base_unit_id, factor, created_at, updated_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, :code, :name, CAST(:base_unit_id AS uuid),
                             CAST(:factor AS bigint), CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                    [
                        'id' => $u->id,
                        'tenant_id' => $u->tenantId,
                        'code' => $u->code,
                        'name' => $u->name,
                        'base_unit_id' => $u->baseUnitId,
                        'factor' => $u->factor,
                        'created_at' => Ts::fmt($u->createdAt),
                        'updated_at' => Ts::fmt($u->updatedAt),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                throw DomainException::of(ErrorCode::CodeAlreadyTaken);
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function insertWithIngredient(Ingredient $i, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($i, $evt): void {
            try {
                $tx->executeStatement(
                    'INSERT INTO ingredients (id, tenant_id, sku, name, default_unit_id, low_stock_threshold_qty, created_at, updated_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, :sku, :name, CAST(:default_unit_id AS uuid),
                             CAST(:threshold AS bigint), CAST(:created_at AS timestamptz), CAST(:updated_at AS timestamptz))',
                    [
                        'id' => $i->id,
                        'tenant_id' => $i->tenantId,
                        'sku' => $i->sku,
                        'name' => $i->name,
                        'default_unit_id' => $i->defaultUnitId,
                        'threshold' => $i->lowStockThresholdQty,
                        'created_at' => Ts::fmt($i->createdAt),
                        'updated_at' => Ts::fmt($i->updatedAt),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                throw DomainException::of(ErrorCode::SKUAlreadyTaken);
            }
            $this->insertOutbox($tx, $evt);
        });
    }

    public function replaceRecipe(string $productId, array $lines, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($productId, $lines, $evt): void {
            $tx->executeStatement(
                'DELETE FROM recipe_lines WHERE product_id = CAST(:pid AS uuid)',
                ['pid' => $productId],
            );
            foreach ($lines as $l) {
                try {
                    $tx->executeStatement(
                        'INSERT INTO recipe_lines (id, product_id, ingredient_id, qty, unit_id, created_at)
                         VALUES (CAST(:id AS uuid), CAST(:product_id AS uuid), CAST(:ingredient_id AS uuid),
                                 CAST(:qty AS bigint), CAST(:unit_id AS uuid), CAST(:created_at AS timestamptz))',
                        [
                            'id' => $l->id,
                            'product_id' => $l->productId,
                            'ingredient_id' => $l->ingredientId,
                            'qty' => $l->qty,
                            'unit_id' => $l->unitId,
                            'created_at' => Ts::fmt($l->createdAt),
                        ],
                    );
                } catch (UniqueConstraintViolationException) {
                    throw DomainException::of(ErrorCode::DuplicateRecipeLine);
                }
            }
            // Touch products.updated_at so observers see the recipe revision
            // without subscribing to events.
            $tx->executeStatement(
                'UPDATE products SET updated_at = CAST(:at AS timestamptz) WHERE id = CAST(:pid AS uuid)',
                ['at' => Ts::fmt($evt->occurredAt), 'pid' => $productId],
            );
            $this->insertOutbox($tx, $evt);
        });
    }

    public function fetchUnpublished(int $limit): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT event_id, schema, tenant_id, payload
             FROM outbox_events
             WHERE published_at IS NULL
             ORDER BY occurred_at ASC
             LIMIT CAST(:limit AS integer)',
            ['limit' => $limit],
        );

        return array_map(
            static fn (array $r): OutboxRow => new OutboxRow(
                eventId: (string) $r['event_id'],
                schema: (string) $r['schema'],
                tenantId: (string) $r['tenant_id'],
                payload: (string) $r['payload'],
            ),
            $rows,
        );
    }

    public function markPublished(array $eventIds, \DateTimeImmutable $at): void
    {
        if ($eventIds === []) {
            return;
        }
        $this->db->executeStatement(
            'UPDATE outbox_events SET published_at = CAST(:at AS timestamptz)
             WHERE event_id = ANY(CAST(:ids AS uuid[]))',
            ['at' => Ts::fmt($at), 'ids' => PgArray::encode($eventIds)],
        );
    }

    private function insertOutbox(Connection $tx, OutboxEvent $evt): void
    {
        $tx->executeStatement(
            'INSERT INTO outbox_events
                (event_id, aggregate_id, aggregate_type, schema, payload, occurred_at, tenant_id, trace_id)
             VALUES (CAST(:event_id AS uuid), CAST(:aggregate_id AS uuid), :aggregate_type, :schema,
                     CAST(:payload AS jsonb), CAST(:occurred_at AS timestamptz), :tenant_id, :trace_id)',
            [
                'event_id' => $evt->eventId,
                'aggregate_id' => $evt->aggregateId,
                'aggregate_type' => $evt->aggregateType,
                'schema' => $evt->schema,
                'payload' => $evt->payload,
                'occurred_at' => Ts::fmt($evt->occurredAt),
                'tenant_id' => $evt->tenantId,
                'trace_id' => $evt->traceId,
            ],
        );
    }
}
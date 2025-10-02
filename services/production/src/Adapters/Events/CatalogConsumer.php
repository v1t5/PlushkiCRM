<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Production\Platform\Events\Envelope;
use Plushki\Production\Platform\Events\PoisonException;
use Plushki\Production\Ports\RecipeLine;
use Plushki\Production\Ports\RecipeProjection;
use Plushki\Production\Ports\RecipeProjectionRepo;

/**
 * Maintains recipe_projection from catalog.v1.recipe_updated.#. The snapshot
 * lets task_completed attach the BOM without a catalog round-trip. Outcome:
 * return=ack, PoisonException=drop, throw=requeue.
 */
final class CatalogConsumer
{
    public function __construct(
        private readonly RecipeProjectionRepo $projection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        $productId = (string) ($d['product_id'] ?? '');
        if (!Uuid::isValid($productId)) {
            $this->logger->warning('product id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid product_id');
        }

        $lines = [];
        foreach ((array) ($d['lines'] ?? []) as $l) {
            if (!\is_array($l)) {
                continue;
            }
            $ingId = (string) ($l['ingredient_id'] ?? '');
            $unitId = (string) ($l['unit_id'] ?? '');
            if (!Uuid::isValid($ingId) || !Uuid::isValid($unitId)) {
                throw new PoisonException('invalid recipe line id');
            }
            $lines[] = new RecipeLine(
                ingredientId: $ingId,
                ingredientSku: (string) ($l['ingredient_sku'] ?? ''),
                ingredientName: (string) ($l['ingredient_name'] ?? ''),
                qty: (int) ($l['qty'] ?? 0),
                unitId: $unitId,
                unitCode: (string) ($l['unit_code'] ?? ''),
                unitFactor: (int) ($l['unit_factor'] ?? 0),
                qtyInBase: (int) ($l['qty_in_base'] ?? 0),
            );
        }

        $this->projection->upsert(new RecipeProjection(
            productId: $productId,
            tenantId: $env->tenantId,
            productSku: (string) ($d['product_sku'] ?? ''),
            lines: $lines,
            updatedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ));
    }
}

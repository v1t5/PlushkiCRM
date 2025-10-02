<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Events;

use Psr\Log\LoggerInterface;
use Plushki\Inventory\Platform\Events\Envelope;
use Plushki\Inventory\Platform\Events\PoisonException;
use Plushki\Inventory\Ports\IngredientProjection;
use Plushki\Inventory\Ports\IngredientProjectionRepo;

/**
 * CatalogConsumer maintains the ingredient_projection table from
 * catalog.v1.ingredient_created.#. The catalog threshold is expressed in the
 * ingredient's default unit; we convert to base units once here so movement-time
 * comparisons are trivial.
 *
 * Outcome → generic-Consumer contract: return = ack, PoisonException = drop,
 * any other throw = nack-requeue.
 */
final class CatalogConsumer
{
    public function __construct(
        private readonly IngredientProjectionRepo $projection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        $ingredientId = (string) ($d['ingredient_id'] ?? '');
        if (!\Symfony\Component\Uid\Uuid::isValid($ingredientId)) {
            $this->logger->warning('ingredient id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid ingredient_id');
        }

        $factor = (int) ($d['default_unit_factor'] ?? 0);
        $thresholdInBase = (int) ($d['low_stock_threshold_qty'] ?? 0) * $factor;

        $this->projection->upsert(new IngredientProjection(
            ingredientId: $ingredientId,
            tenantId: $env->tenantId,
            sku: (string) ($d['sku'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            defaultUnitCode: (string) ($d['default_unit_code'] ?? ''),
            defaultUnitFactor: $factor,
            thresholdQtyInBase: $thresholdInBase,
            updatedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ));
    }
}

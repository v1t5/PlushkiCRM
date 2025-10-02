<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\Envelope;
use Plushki\Catalog\Ports\IngredientRepo;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;
use Plushki\Catalog\Ports\UnitRepo;

/**
 * Manages raw inputs consumed by production. The created event carries the
 * default unit's code/factor so inventory can translate the threshold to base units.
 */
final class IngredientService
{
    public function __construct(
        private readonly IngredientRepo $ingredients,
        private readonly UnitRepo $units,
        private readonly OutboxRepo $outbox,
    ) {
    }

    public function create(string $sku, string $name, string $defaultUnitId, int $lowStockThresholdQty): Ingredient
    {
        $unit = $this->units->getById($defaultUnitId);
        if ($unit->isArchived()) {
            throw DomainException::of(ErrorCode::UnitArchived);
        }
        $i = Ingredient::create($sku, $name, $defaultUnitId, $lowStockThresholdQty);
        $this->outbox->insertWithIngredient($i, $this->ingredientCreatedEvent($i, $unit));

        return $i;
    }

    public function get(string $id): Ingredient
    {
        return $this->ingredients->getById($id);
    }

    /** @return list<Ingredient> */
    public function list(string $tenantId): array
    {
        return $this->ingredients->listActive($tenantId !== '' ? $tenantId : 'default');
    }

    private function ingredientCreatedEvent(Ingredient $i, Unit $unit): OutboxEvent
    {
        $schema = 'catalog.v1.ingredient_created';
        $env = Envelope::build(
            schema: $schema,
            data: [
                'ingredient_id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'default_unit_id' => $i->defaultUnitId,
                'default_unit_code' => $unit->code,
                'default_unit_factor' => $unit->factor,
                'low_stock_threshold_qty' => $i->lowStockThresholdQty,
            ],
            actorType: 'system',
            actorId: 'catalog',
            occurredAt: $i->createdAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $i->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $i->id,
            aggregateType: 'ingredient',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $i->createdAt,
            tenantId: $i->tenantId,
            traceId: $env->traceId,
        );
    }
}

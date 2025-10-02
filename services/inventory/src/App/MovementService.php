<?php

declare(strict_types=1);

namespace Plushki\Inventory\App;

use Psr\Log\LoggerInterface;
use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\MovementType;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\StockMovement;
use Plushki\Inventory\Platform\Events\Envelope;
use Plushki\Inventory\Ports\IngredientProjectionRepo;
use Plushki\Inventory\Ports\MovementRepo;
use Plushki\Inventory\Ports\OutboxEvent;
use Plushki\Inventory\Ports\OutboxRepo;
use Plushki\Inventory\Ports\StockRepo;
use Plushki\Inventory\Ports\WarehouseRepo;

/**
 * MovementService is the only writer of stock_movements. It owns low-stock
 * detection: after a batch posts, it consults the ingredient projection and
 * emits inventory.v1.stock_low for every ingredient that has just crossed below
 * its threshold. Every movement (manual or consumer-driven) also emits
 * inventory.v1.movement_posted inside the same transaction as the ledger row,
 * for the reporting stream.
 */
final class MovementService
{
    private const MOVEMENT_POSTED = 'inventory.v1.movement_posted';
    private const STOCK_LOW = 'inventory.v1.stock_low';

    public function __construct(
        private readonly MovementRepo $movements,
        private readonly WarehouseRepo $warehouses,
        private readonly StockRepo $stock,
        private readonly IngredientProjectionRepo $ingredients,
        private readonly OutboxRepo $outbox,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Post applies a single manual movement (HTTP path). The movement_posted
     * event is written in the same transaction; low-stock detection runs after.
     *
     * @return array{0: StockMovement, 1: StockLevel}
     */
    public function post(PostMovementInput $in): array
    {
        $w = $this->warehouses->getById($in->warehouseId);
        if ($w->isArchived()) {
            throw DomainException::of(ErrorCode::WarehouseArchived);
        }
        $m = StockMovement::create($in->warehouseId, $in->itemKind, $in->itemId, $in->type, $in->qtyInBase, $in->reason, null, null);
        $evt = $this->buildMovementPostedEvent($m);
        [$mv, $lvl] = $this->movements->post($m, [$evt]);
        $this->maybeEmitLowStock($lvl, $in->qtyInBase);

        return [$mv, $lvl];
    }

    /**
     * ApplyOrderFulfillment turns one orders.v1.fulfilled envelope into a SOLD
     * movement per product line. Idempotent at the DB layer via the unique
     * (source_event_id, item_kind, item_id) index.
     *
     * @param list<EventLine> $lines
     */
    public function applyOrderFulfillment(string $eventId, string $warehouseId, ?\DateTimeImmutable $occurredAt, array $lines): void
    {
        $this->applyBatch($eventId, $warehouseId, $occurredAt, $lines, MovementType::Sold, 'orders.v1.fulfilled');
    }

    /**
     * ApplyTaskCompleted turns one production.v1.task_completed envelope into a
     * CONSUMED_BY_PRODUCTION movement per ingredient line.
     *
     * @param list<EventLine> $lines
     */
    public function applyTaskCompleted(string $eventId, string $warehouseId, ?\DateTimeImmutable $occurredAt, array $lines): void
    {
        $this->applyBatch($eventId, $warehouseId, $occurredAt, $lines, MovementType::ConsumedByProduction, 'production.v1.task_completed');
    }

    /**
     * @param list<EventLine> $lines
     */
    private function applyBatch(string $eventId, string $warehouseId, ?\DateTimeImmutable $occurredAt, array $lines, MovementType $type, string $reason): void
    {
        if ($lines === []) {
            return;
        }
        $movements = [];
        foreach ($lines as $l) {
            // Deduction — sign-flip the magnitude.
            $movements[] = StockMovement::create(
                $warehouseId,
                $l->itemKind,
                $l->itemId,
                $type,
                -\abs($l->qtyInBase),
                $reason,
                $eventId,
                $occurredAt,
            );
        }
        $evts = $this->buildMovementPostedEvents($movements);
        [, $levels, $alreadyApplied] = $this->movements->postBatch($movements, $evts);
        if ($alreadyApplied) {
            return;
        }
        foreach ($levels as $i => $lvl) {
            $this->maybeEmitLowStock($lvl, $movements[$i]->qtyInBase);
        }
    }

    /**
     * maybeEmitLowStock fires inventory.v1.stock_low when an ingredient has just
     * crossed below its threshold. Only checked on a deduction (delta < 0); the
     * "just crossed" guard (previous level was above) avoids re-firing for an
     * already-low ingredient.
     */
    private function maybeEmitLowStock(StockLevel $lvl, int $delta): void
    {
        if ($lvl->itemKind !== ItemKind::Ingredient || $delta >= 0) {
            return;
        }
        $proj = $this->ingredients->get($lvl->itemId);
        if ($proj === null || $proj->thresholdQtyInBase <= 0) {
            return;
        }
        $previous = $lvl->qtyInBase - $delta; // delta is negative here
        if ($lvl->qtyInBase > $proj->thresholdQtyInBase) {
            return;
        }
        if ($previous <= $proj->thresholdQtyInBase) {
            return; // already below before this movement — don't re-fire
        }
        try {
            $envelope = Envelope::build(
                schema: self::STOCK_LOW,
                data: [
                    'ingredient_id' => $proj->ingredientId,
                    'sku' => $proj->sku,
                    'name' => $proj->name,
                    'warehouse_id' => $lvl->warehouseId,
                    'qty_in_base' => $lvl->qtyInBase,
                    'threshold_qty_in_base' => $proj->thresholdQtyInBase,
                    'default_unit_code' => $proj->defaultUnitCode,
                    'default_unit_factor' => $proj->defaultUnitFactor,
                ],
                actorType: 'system',
                actorId: 'inventory',
                occurredAt: self::nowIso(),
                tenantId: $lvl->tenantId,
            );
            $this->outbox->insert(new OutboxEvent(
                eventId: $envelope->eventId,
                aggregateId: $proj->ingredientId,
                aggregateType: 'ingredient',
                schema: self::STOCK_LOW,
                payload: $envelope->toJson(),
                occurredAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                tenantId: $lvl->tenantId,
                traceId: $envelope->traceId,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('emit stock_low', ['err' => $e->getMessage()]);
        }
    }

    /**
     * @param list<StockMovement> $ms
     * @return list<OutboxEvent>
     */
    private function buildMovementPostedEvents(array $ms): array
    {
        return array_map($this->buildMovementPostedEvent(...), $ms);
    }

    private function buildMovementPostedEvent(StockMovement $m): OutboxEvent
    {
        $itemSku = '';
        $itemName = '';
        if ($m->itemKind === ItemKind::Ingredient) {
            $proj = $this->ingredients->get($m->itemId);
            if ($proj !== null) {
                $itemSku = $proj->sku;
                $itemName = $proj->name;
            }
        }
        $occurredIso = $m->occurredAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
        $data = [
            'movement_id' => $m->id,
            'warehouse_id' => $m->warehouseId,
            'item_kind' => $m->itemKind->value,
            'item_id' => $m->itemId,
            'item_sku' => $itemSku,
            'item_name' => $itemName,
            'type' => $m->type->value,
            'qty_in_base' => $m->qtyInBase,
            'reason' => $m->reason,
            'occurred_at' => $occurredIso,
        ];
        if ($m->sourceEventId !== null) {
            $data['source_event_id'] = $m->sourceEventId;
        }
        $envelope = Envelope::build(
            schema: self::MOVEMENT_POSTED,
            data: $data,
            actorType: 'system',
            actorId: 'inventory',
            occurredAt: $occurredIso,
            tenantId: $m->tenantId,
        );

        return new OutboxEvent(
            eventId: $envelope->eventId,
            aggregateId: $m->id,
            aggregateType: 'stock_movement',
            schema: self::MOVEMENT_POSTED,
            payload: $envelope->toJson(),
            occurredAt: $m->occurredAt,
            tenantId: $m->tenantId,
            traceId: $envelope->traceId,
        );
    }

    private static function nowIso(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP');
    }
}

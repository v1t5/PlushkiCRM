<?php

declare(strict_types=1);

namespace Plushki\Inventory\Tests\Fake;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\StockMovement;
use Plushki\Inventory\Ports\MovementRepo;
use Plushki\Inventory\Ports\OutboxEvent;

/**
 * Array-backed MovementRepo. Applies each movement's signed qty to an internal
 * running total (the StockLevel it returns), records posted movements and the
 * in-transaction outbox events, and simulates the DB idempotency key:
 * a batch whose sourceEventId was already applied returns alreadyApplied=true
 * and writes no events.
 */
final class InMemoryMovementRepo implements MovementRepo
{
    /** @var list<StockMovement> */
    public array $posted = [];

    /** @var list<OutboxEvent> events written in-transaction by post/postBatch */
    public array $events = [];

    /** @var array<string, int> running totals keyed by (warehouse|kind|item) */
    private array $totals = [];

    /** @var array<string, true> sourceEventIds already applied */
    private array $appliedEvents = [];

    public function seedTotal(string $warehouseId, ItemKind $kind, string $itemId, int $qtyInBase): void
    {
        $this->totals[self::key($warehouseId, $kind, $itemId)] = $qtyInBase;
    }

    /**
     * @param list<OutboxEvent> $evts
     * @return array{0: StockMovement, 1: StockLevel}
     */
    public function post(StockMovement $m, array $evts): array
    {
        $this->posted[] = $m;
        foreach ($evts as $e) {
            $this->events[] = $e;
        }
        $lvl = $this->apply($m);

        return [$m, $lvl];
    }

    /**
     * @param list<StockMovement> $ms
     * @param list<OutboxEvent> $evts
     * @return array{0: list<StockMovement>, 1: list<StockLevel>, 2: bool}
     */
    public function postBatch(array $ms, array $evts): array
    {
        $eventId = $ms === [] ? null : $ms[0]->sourceEventId;
        if ($eventId !== null && isset($this->appliedEvents[$eventId])) {
            return [[], [], true];
        }
        if ($eventId !== null) {
            $this->appliedEvents[$eventId] = true;
        }
        $levels = [];
        foreach ($ms as $m) {
            $this->posted[] = $m;
            $levels[] = $this->apply($m);
        }
        foreach ($evts as $e) {
            $this->events[] = $e;
        }

        return [$ms, $levels, false];
    }

    /** @return list<StockMovement> */
    public function listByItem(ItemKind $kind, string $itemId, int $limit): array
    {
        $out = [];
        foreach ($this->posted as $m) {
            if ($m->itemKind === $kind && $m->itemId === $itemId) {
                $out[] = $m;
            }
        }

        return \array_slice($out, 0, $limit);
    }

    private function apply(StockMovement $m): StockLevel
    {
        $k = self::key($m->warehouseId, $m->itemKind, $m->itemId);
        $this->totals[$k] = ($this->totals[$k] ?? 0) + $m->qtyInBase;

        return new StockLevel($m->tenantId, $m->warehouseId, $m->itemKind, $m->itemId, $this->totals[$k]);
    }

    private static function key(string $warehouseId, ItemKind $kind, string $itemId): string
    {
        return $warehouseId . '|' . $kind->value . '|' . $itemId;
    }
}

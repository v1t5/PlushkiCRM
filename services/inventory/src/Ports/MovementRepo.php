<?php

declare(strict_types=1);

namespace Plushki\Inventory\Ports;

use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Domain\StockLevel;
use Plushki\Inventory\Domain\StockMovement;

/**
 * MovementRepo writes stock_movements rows together with the running-total
 * upserts and any outbox rows in a single transaction.
 */
interface MovementRepo
{
    /**
     * Post a single manual movement. Events in $evts are inserted into the
     * outbox in the same transaction.
     *
     * @param list<OutboxEvent> $evts
     * @return array{0: StockMovement, 1: StockLevel}
     */
    public function post(StockMovement $m, array $evts): array;

    /**
     * PostBatch atomically posts multiple movements sharing one sourceEventId.
     * If the batch was already applied (idempotency key collision), the
     * existing rows are returned and the outbox events are NOT written again.
     *
     * @param list<StockMovement> $ms
     * @param list<OutboxEvent> $evts
     * @return array{0: list<StockMovement>, 1: list<StockLevel>, 2: bool} [movements, levels, alreadyApplied]
     */
    public function postBatch(array $ms, array $evts): array;

    /**
     * @return list<StockMovement>
     */
    public function listByItem(ItemKind $kind, string $itemId, int $limit): array;
}

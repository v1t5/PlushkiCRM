<?php

declare(strict_types=1);

namespace Plushki\Production\App;

use Psr\Log\LoggerInterface;
use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;
use Plushki\Production\Platform\Events\Envelope;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\RecipeProjectionRepo;
use Plushki\Production\Ports\TaskRepo;

/**
 * Runs the task FSM. Complete snapshots the recipe from the projection table and
 * embeds the per-ingredient base-unit qty (scaled by task qty) in the
 * task_completed payload so inventory deducts without calling catalog.
 */
final class TaskService
{
    private const TASK_COMPLETED = 'production.v1.task_completed';

    public function __construct(
        private readonly TaskRepo $tasks,
        private readonly RecipeProjectionRepo $recipes,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $id): Task
    {
        return $this->tasks->getById($id);
    }

    /** @return list<Task> */
    public function list(?string $planId, ?TaskStatus $status): array
    {
        return $this->tasks->list($planId, $status);
    }

    public function start(string $id, ?string $bakerId): Task
    {
        $t = $this->tasks->getById($id);
        $t->start($bakerId);
        $this->tasks->updateStarted($t);

        return $t;
    }

    public function cancel(string $id): Task
    {
        $t = $this->tasks->getById($id);
        $t->cancel();
        $this->tasks->updateCancelled($t);

        return $t;
    }

    /**
     * Complete moves the task to 'completed' and emits task_completed with the
     * snapshotted recipe lines. Missing projection → empty lines (inventory
     * deducts nothing), with a warning.
     */
    public function complete(string $id): Task
    {
        $t = $this->tasks->getById($id);
        $t->complete();

        $recipe = $this->recipes->get($t->productId);
        if ($recipe === null) {
            $this->logger->warning('no recipe projection — task_completed will carry empty lines', [
                'task_id' => $t->id,
                'product_id' => $t->productId,
            ]);
        }

        $evt = $this->taskCompletedEvent($t, $recipe);
        $this->tasks->updateCompletedWithEvent($t, $evt);

        return $t;
    }

    private function taskCompletedEvent(Task $t, ?\Plushki\Production\Ports\RecipeProjection $recipe): OutboxEvent
    {
        $lineData = [];
        foreach ($recipe?->lines ?? [] as $l) {
            $lineData[] = [
                'ingredient_id' => $l->ingredientId,
                'ingredient_sku' => $l->ingredientSku,
                'ingredient_name' => $l->ingredientName,
                'qty_in_base' => $l->qtyInBase * $t->qty,
                'unit_code' => $l->unitCode,
                'unit_factor' => $l->unitFactor,
            ];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $envelope = Envelope::build(
            schema: self::TASK_COMPLETED,
            data: [
                'task_id' => $t->id,
                'plan_id' => $t->planId,
                'product_id' => $t->productId,
                'qty' => $t->qty,
                'lines' => $lineData,
            ],
            actorType: 'system',
            actorId: 'production',
            occurredAt: $now->format('Y-m-d\TH:i:s.uP'),
            tenantId: $t->tenantId,
        );

        return new OutboxEvent(
            eventId: $envelope->eventId,
            aggregateId: $t->id,
            aggregateType: 'task',
            schema: self::TASK_COMPLETED,
            payload: $envelope->toJson(),
            occurredAt: $now,
            tenantId: $t->tenantId,
            traceId: $envelope->traceId,
        );
    }
}

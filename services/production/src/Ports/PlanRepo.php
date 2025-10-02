<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\PlanItem;
use Plushki\Production\Domain\Task;

/**
 * Handles draft plans + the publish transition. Accumulation goes through a
 * single atomic entry point so the idempotency gate, plan upsert, and
 * plan_items upsert land in one transaction.
 */
interface PlanRepo
{
    /**
     * @return array{0: Plan, 1: list<PlanItem>}
     * @throws \Plushki\Production\Domain\DomainException PlanNotFound
     */
    public function getByDate(string $tenantId, \DateTimeImmutable $planDate): array;

    /**
     * Atomically: insert applied_order_lines (event_id, product_id) — skipping
     * the rest on conflict; upsert the (tenant, date) draft plan; bump
     * plan_items.qty for (plan, product). Idempotent under redelivery.
     *
     * @throws \Plushki\Production\Domain\DomainException PlanAlreadyPublished
     */
    public function accumulateConfirmedLine(string $eventId, \DateTimeImmutable $planDate, string $productId, int $qty): void;

    /**
     * Flip plan to 'published', materialise tasks (one per plan_item), and write
     * the plan_published outbox row in the same transaction.
     *
     * @param list<Task> $tasks
     * @return array{0: Plan, 1: list<Task>}
     */
    public function publishWithTasks(Plan $plan, array $tasks, OutboxEvent $evt): array;
}

<?php

declare(strict_types=1);

namespace Plushki\Production\App;

use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\Task;
use Plushki\Production\Platform\Events\Envelope;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\PlanRepo;

/**
 * Owns the day's draft plan: the orders.v1.confirmed accumulator and the publish
 * step that materialises tasks and emits production.v1.plan_published.
 */
final class PlanService
{
    private const PLAN_PUBLISHED = 'production.v1.plan_published';

    public function __construct(private readonly PlanRepo $plans)
    {
    }

    /**
     * AccumulateConfirmedLine is called by the orders.v1.confirmed consumer for
     * each item line. $eventId is the idempotency key per (event_id, product_id).
     */
    public function accumulateConfirmedLine(string $eventId, \DateTimeImmutable $planDate, string $productId, int $qty): void
    {
        if ($qty <= 0) {
            throw DomainException::of(ErrorCode::InvalidQty);
        }
        $this->plans->accumulateConfirmedLine($eventId, self::normalizeDate($planDate), $productId, $qty);
    }

    /**
     * @return array{0: Plan, 1: list<\Plushki\Production\Domain\PlanItem>}
     */
    public function getByDate(\DateTimeImmutable $planDate): array
    {
        return $this->plans->getByDate('default', self::normalizeDate($planDate));
    }

    /**
     * Publish freezes the plan, materialises tasks, and emits plan_published.
     *
     * @return array{0: Plan, 1: list<Task>}
     */
    public function publish(\DateTimeImmutable $planDate): array
    {
        [$plan, $items] = $this->plans->getByDate('default', self::normalizeDate($planDate));
        if ($plan->isPublished()) {
            throw DomainException::of(ErrorCode::PlanAlreadyPublished);
        }
        if ($items === []) {
            throw DomainException::of(ErrorCode::PlanEmpty);
        }

        $tasks = [];
        foreach ($items as $it) {
            $tasks[] = Task::create($plan->id, $it->productId, $it->qty);
        }

        $evt = $this->planPublishedEvent($plan, $items, $tasks);

        return $this->plans->publishWithTasks($plan, $tasks, $evt);
    }

    /**
     * @param list<\Plushki\Production\Domain\PlanItem> $items
     * @param list<Task> $tasks
     */
    private function planPublishedEvent(Plan $plan, array $items, array $tasks): OutboxEvent
    {
        $itemData = [];
        foreach ($items as $it) {
            $itemData[] = ['product_id' => $it->productId, 'qty' => $it->qty];
        }
        $taskData = [];
        foreach ($tasks as $t) {
            $taskData[] = ['task_id' => $t->id, 'product_id' => $t->productId, 'qty' => $t->qty];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $envelope = Envelope::build(
            schema: self::PLAN_PUBLISHED,
            data: [
                'plan_id' => $plan->id,
                'plan_date' => $plan->planDate->format('Y-m-d'),
                'items' => $itemData,
                'tasks' => $taskData,
            ],
            actorType: 'system',
            actorId: 'production',
            occurredAt: $now->format('Y-m-d\TH:i:s.uP'),
            tenantId: $plan->tenantId,
        );

        return new OutboxEvent(
            eventId: $envelope->eventId,
            aggregateId: $plan->id,
            aggregateType: 'plan',
            schema: self::PLAN_PUBLISHED,
            payload: $envelope->toJson(),
            occurredAt: $now,
            tenantId: $plan->tenantId,
            traceId: $envelope->traceId,
        );
    }

    /**
     * Strip the time-of-day so two events on the same calendar day land on the
     * same plan. Standardise on UTC midnight.
     */
    private static function normalizeDate(\DateTimeImmutable $t): \DateTimeImmutable
    {
        return $t->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0, 0);
    }
}

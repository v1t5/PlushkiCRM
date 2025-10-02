<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Fake;

use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\PlanItem;
use Plushki\Production\Domain\PlanStatus;
use Plushki\Production\Domain\Task;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\PlanRepo;
use Symfony\Component\Uid\Uuid;

/**
 * Array-backed PlanRepo. Models the atomic accumulate / publish entry points the
 * real DB repo guarantees, plus an idempotency gate keyed on (event_id, product_id).
 */
final class FakePlanRepo implements PlanRepo
{
    /** @var array<string, Plan> keyed by 'tenant|Y-m-d' */
    private array $plans = [];

    /** @var array<string, array<string, PlanItem>> plan key -> product_id -> item */
    private array $items = [];

    /** @var array<string, true> idempotency gate: 'event_id|product_id' */
    private array $applied = [];

    /** @var list<OutboxEvent> */
    public array $published = [];

    private function key(string $tenantId, \DateTimeImmutable $planDate): string
    {
        return $tenantId . '|' . $planDate->format('Y-m-d');
    }

    public function getByDate(string $tenantId, \DateTimeImmutable $planDate): array
    {
        $k = $this->key($tenantId, $planDate);
        if (!isset($this->plans[$k])) {
            throw DomainException::of(ErrorCode::PlanNotFound);
        }

        return [$this->plans[$k], array_values($this->items[$k] ?? [])];
    }

    public function accumulateConfirmedLine(string $eventId, \DateTimeImmutable $planDate, string $productId, int $qty): void
    {
        $gate = $eventId . '|' . $productId;
        if (isset($this->applied[$gate])) {
            return; // idempotent: redelivery is a no-op
        }
        $this->applied[$gate] = true;

        $k = $this->key('default', $planDate);
        if (!isset($this->plans[$k])) {
            $this->plans[$k] = Plan::create($planDate);
            $this->items[$k] = [];
        }

        if ($this->plans[$k]->isPublished()) {
            throw DomainException::of(ErrorCode::PlanAlreadyPublished);
        }

        $plan = $this->plans[$k];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (isset($this->items[$k][$productId])) {
            $existing = $this->items[$k][$productId];
            $this->items[$k][$productId] = new PlanItem(
                $existing->id,
                $existing->planId,
                $productId,
                $existing->qty + $qty,
                $existing->createdAt,
                $now,
            );
        } else {
            $this->items[$k][$productId] = new PlanItem(
                Uuid::v7()->toRfc4122(),
                $plan->id,
                $productId,
                $qty,
                $now,
                $now,
            );
        }
    }

    public function publishWithTasks(Plan $plan, array $tasks, OutboxEvent $evt): array
    {
        $plan->status = PlanStatus::Published;
        $plan->publishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->published[] = $evt;

        return [$plan, $tasks];
    }

    /** Test helper: seed a draft plan with already-accumulated items. */
    public function seedDraft(\DateTimeImmutable $planDate, array $productQty): Plan
    {
        $k = $this->key('default', $planDate);
        $plan = Plan::create($planDate);
        $this->plans[$k] = $plan;
        $this->items[$k] = [];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($productQty as $productId => $qty) {
            $this->items[$k][$productId] = new PlanItem(
                Uuid::v7()->toRfc4122(),
                $plan->id,
                (string) $productId,
                (int) $qty,
                $now,
                $now,
            );
        }

        return $plan;
    }
}

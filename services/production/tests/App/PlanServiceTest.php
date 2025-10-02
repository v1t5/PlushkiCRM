<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\App;

use Plushki\Production\App\PlanService;
use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\PlanStatus;
use Plushki\Production\Domain\TaskStatus;
use Plushki\Production\Tests\Fake\FakePlanRepo;
use PHPUnit\Framework\TestCase;

final class PlanServiceTest extends TestCase
{
    private function date(string $ymd = '2026-06-06'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd . 'T00:00:00', new \DateTimeZone('UTC'));
    }

    public function testAccumulateCreatesDraftPlanWithItem(): void
    {
        $repo = new FakePlanRepo();
        $svc = new PlanService($repo);

        $svc->accumulateConfirmedLine('evt-1', $this->date(), 'product-1', 3);

        [$plan, $items] = $svc->getByDate($this->date());
        self::assertSame(PlanStatus::Draft, $plan->status);
        self::assertCount(1, $items);
        self::assertSame('product-1', $items[0]->productId);
        self::assertSame(3, $items[0]->qty);
    }

    public function testAccumulateSumsSameProductAcrossEvents(): void
    {
        $repo = new FakePlanRepo();
        $svc = new PlanService($repo);

        $svc->accumulateConfirmedLine('evt-1', $this->date(), 'product-1', 3);
        $svc->accumulateConfirmedLine('evt-2', $this->date(), 'product-1', 4);

        [, $items] = $svc->getByDate($this->date());
        self::assertCount(1, $items);
        self::assertSame(7, $items[0]->qty);
    }

    public function testAccumulateIsIdempotentPerEventAndProduct(): void
    {
        $repo = new FakePlanRepo();
        $svc = new PlanService($repo);

        $svc->accumulateConfirmedLine('evt-1', $this->date(), 'product-1', 3);
        $svc->accumulateConfirmedLine('evt-1', $this->date(), 'product-1', 3); // redelivery

        [, $items] = $svc->getByDate($this->date());
        self::assertSame(3, $items[0]->qty);
    }

    public function testAccumulateRejectsNonPositiveQty(): void
    {
        $svc = new PlanService(new FakePlanRepo());

        try {
            $svc->accumulateConfirmedLine('evt-1', $this->date(), 'product-1', 0);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }

    public function testAccumulateNormalisesTimeOfDayToSameCalendarPlan(): void
    {
        $repo = new FakePlanRepo();
        $svc = new PlanService($repo);

        $morning = new \DateTimeImmutable('2026-06-06T07:30:00', new \DateTimeZone('UTC'));
        $evening = new \DateTimeImmutable('2026-06-06T22:15:00', new \DateTimeZone('UTC'));
        $svc->accumulateConfirmedLine('evt-1', $morning, 'product-1', 2);
        $svc->accumulateConfirmedLine('evt-2', $evening, 'product-1', 5);

        [, $items] = $svc->getByDate($this->date());
        self::assertCount(1, $items);
        self::assertSame(7, $items[0]->qty);
    }

    public function testPublishMaterialisesTasksAndEmitsPlanPublished(): void
    {
        $repo = new FakePlanRepo();
        $repo->seedDraft($this->date(), ['product-1' => 3, 'product-2' => 5]);
        $svc = new PlanService($repo);

        [$plan, $tasks] = $svc->publish($this->date());

        self::assertSame(PlanStatus::Published, $plan->status);
        self::assertNotNull($plan->publishedAt);
        self::assertCount(2, $tasks);
        foreach ($tasks as $t) {
            self::assertSame(TaskStatus::Open, $t->status);
            self::assertSame($plan->id, $t->planId);
        }

        // exactly one plan_published event emitted in the same transaction
        self::assertCount(1, $repo->published);
        $evt = $repo->published[0];
        self::assertSame('production.v1.plan_published', $evt->schema);
        self::assertSame('plan', $evt->aggregateType);
        self::assertSame($plan->id, $evt->aggregateId);

        $decoded = json_decode($evt->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('production.v1.plan_published', $decoded['schema']);
        self::assertSame($plan->id, $decoded['data']['plan_id']);
        self::assertSame('2026-06-06', $decoded['data']['plan_date']);
        self::assertCount(2, $decoded['data']['items']);
        self::assertCount(2, $decoded['data']['tasks']);
        // task payload carries the materialised task ids
        $taskIds = array_column($decoded['data']['tasks'], 'task_id');
        foreach ($tasks as $t) {
            self::assertContains($t->id, $taskIds);
        }
    }

    public function testPublishRejectsEmptyPlan(): void
    {
        $repo = new FakePlanRepo();
        $repo->seedDraft($this->date(), []);
        $svc = new PlanService($repo);

        try {
            $svc->publish($this->date());
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::PlanEmpty, $e->errorCode);
        }
        self::assertCount(0, $repo->published);
    }

    public function testPublishRejectsAlreadyPublishedPlan(): void
    {
        $repo = new FakePlanRepo();
        $plan = $repo->seedDraft($this->date(), ['product-1' => 1]);
        $plan->status = PlanStatus::Published;
        $svc = new PlanService($repo);

        try {
            $svc->publish($this->date());
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::PlanAlreadyPublished, $e->errorCode);
        }
        self::assertCount(0, $repo->published);
    }

    public function testPublishRejectsMissingPlan(): void
    {
        $svc = new PlanService(new FakePlanRepo());

        try {
            $svc->publish($this->date());
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::PlanNotFound, $e->errorCode);
        }
    }
}

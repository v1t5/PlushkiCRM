<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\App;

use Plushki\Production\App\TaskService;
use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;
use Plushki\Production\Ports\RecipeLine;
use Plushki\Production\Ports\RecipeProjection;
use Plushki\Production\Tests\Fake\FakeRecipeProjectionRepo;
use Plushki\Production\Tests\Fake\FakeTaskRepo;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TaskServiceTest extends TestCase
{
    private FakeTaskRepo $tasks;
    private FakeRecipeProjectionRepo $recipes;
    private TaskService $svc;

    protected function setUp(): void
    {
        $this->tasks = new FakeTaskRepo();
        $this->recipes = new FakeRecipeProjectionRepo();
        $this->svc = new TaskService($this->tasks, $this->recipes, new NullLogger());
    }

    private function openTask(int $qty = 1, string $planId = 'plan-1', string $productId = 'product-1'): Task
    {
        $t = Task::create($planId, $productId, $qty);
        $this->tasks->save($t);

        return $t;
    }

    public function testStartMovesTaskToInProgress(): void
    {
        $t = $this->openTask();

        $out = $this->svc->start($t->id, 'baker-1');

        self::assertSame(TaskStatus::InProgress, $out->status);
        self::assertSame('baker-1', $out->bakerId);
        self::assertSame(1, $this->tasks->updateStartedCalls);
    }

    public function testCancelMovesTaskToCancelled(): void
    {
        $t = $this->openTask();

        $out = $this->svc->cancel($t->id);

        self::assertSame(TaskStatus::Cancelled, $out->status);
        self::assertSame(1, $this->tasks->updateCancelledCalls);
    }

    public function testStartRejectsIllegalTransition(): void
    {
        $t = $this->openTask();
        $this->svc->start($t->id, null);

        try {
            $this->svc->start($t->id, null); // already in_progress
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTaskTransition, $e->errorCode);
        }
        self::assertSame(1, $this->tasks->updateStartedCalls);
    }

    public function testCancelRejectsTerminalTask(): void
    {
        $t = $this->openTask();
        $this->svc->start($t->id, null);
        $this->svc->complete($t->id);

        try {
            $this->svc->cancel($t->id);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTaskTransition, $e->errorCode);
        }
    }

    public function testGetUnknownTaskThrowsTaskNotFound(): void
    {
        try {
            $this->svc->get('does-not-exist');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::TaskNotFound, $e->errorCode);
        }
    }

    public function testCompleteEmitsTaskCompletedWithSnapshottedScaledLines(): void
    {
        $t = $this->openTask(qty: 4, productId: 'product-1');
        $this->svc->start($t->id, 'baker-1');

        $this->recipes->upsert(new RecipeProjection(
            productId: 'product-1',
            tenantId: 'default',
            productSku: 'SKU-1',
            lines: [
                new RecipeLine(
                    ingredientId: 'ing-1',
                    ingredientSku: 'FLOUR',
                    ingredientName: 'Flour',
                    qty: 0,
                    unitId: 'unit-g',
                    unitCode: 'g',
                    unitFactor: 1,
                    qtyInBase: 250,
                ),
            ],
            updatedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ));

        $out = $this->svc->complete($t->id);

        self::assertSame(TaskStatus::Completed, $out->status);
        self::assertNotNull($out->completedAt);
        self::assertSame(1, $this->tasks->updateCompletedCalls);

        self::assertCount(1, $this->tasks->published);
        $evt = $this->tasks->published[0];
        self::assertSame('production.v1.task_completed', $evt->schema);
        self::assertSame('task', $evt->aggregateType);
        self::assertSame($t->id, $evt->aggregateId);

        $decoded = json_decode($evt->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($t->id, $decoded['data']['task_id']);
        self::assertSame('plan-1', $decoded['data']['plan_id']);
        self::assertSame('product-1', $decoded['data']['product_id']);
        self::assertSame(4, $decoded['data']['qty']);
        self::assertCount(1, $decoded['data']['lines']);
        $line = $decoded['data']['lines'][0];
        self::assertSame('ing-1', $line['ingredient_id']);
        // qty_in_base is scaled by task qty: 250 * 4
        self::assertSame(1000, $line['qty_in_base']);
        self::assertSame('g', $line['unit_code']);
        self::assertSame(1, $line['unit_factor']);
    }

    public function testCompleteWithMissingRecipeEmitsEmptyLines(): void
    {
        $t = $this->openTask(qty: 2, productId: 'no-recipe');
        $this->svc->start($t->id, null);

        $out = $this->svc->complete($t->id);

        self::assertSame(TaskStatus::Completed, $out->status);
        self::assertCount(1, $this->tasks->published);
        $decoded = json_decode($this->tasks->published[0]->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $decoded['data']['lines']);
    }

    public function testCompleteRejectsOpenTaskAndEmitsNothing(): void
    {
        $t = $this->openTask();

        try {
            $this->svc->complete($t->id); // open -> completed illegal
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTaskTransition, $e->errorCode);
        }
        self::assertCount(0, $this->tasks->published);
        self::assertSame(0, $this->tasks->updateCompletedCalls);
    }

    public function testListFiltersByPlanAndStatus(): void
    {
        $a = $this->openTask(planId: 'plan-A', productId: 'p1');
        $this->openTask(planId: 'plan-B', productId: 'p2');
        $this->svc->start($a->id, null);

        $inProgressOnPlanA = $this->svc->list('plan-A', TaskStatus::InProgress);
        self::assertCount(1, $inProgressOnPlanA);
        self::assertSame($a->id, $inProgressOnPlanA[0]->id);

        $openOnPlanA = $this->svc->list('plan-A', TaskStatus::Open);
        self::assertCount(0, $openOnPlanA);

        self::assertCount(2, $this->svc->list(null, null));
    }
}

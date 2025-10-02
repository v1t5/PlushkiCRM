<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Fake;

use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\TaskRepo;

/**
 * Array-backed TaskRepo. updateCompletedWithEvent records the emitted event so
 * usecase tests can assert task_completed was published transactionally.
 */
final class FakeTaskRepo implements TaskRepo
{
    /** @var array<string, Task> */
    private array $store = [];

    /** @var list<OutboxEvent> */
    public array $published = [];

    public int $updateStartedCalls = 0;
    public int $updateCancelledCalls = 0;
    public int $updateCompletedCalls = 0;

    public function save(Task $t): void
    {
        $this->store[$t->id] = $t;
    }

    public function getById(string $id): Task
    {
        if (!isset($this->store[$id])) {
            throw DomainException::of(ErrorCode::TaskNotFound);
        }

        return $this->store[$id];
    }

    public function list(?string $planId, ?TaskStatus $status): array
    {
        $out = [];
        foreach ($this->store as $t) {
            if ($planId !== null && $t->planId !== $planId) {
                continue;
            }
            if ($status !== null && $t->status !== $status) {
                continue;
            }
            $out[] = $t;
        }

        return $out;
    }

    public function updateStarted(Task $t): void
    {
        $this->updateStartedCalls++;
        $this->store[$t->id] = $t;
    }

    public function updateCompletedWithEvent(Task $t, OutboxEvent $evt): void
    {
        $this->updateCompletedCalls++;
        $this->store[$t->id] = $t;
        $this->published[] = $evt;
    }

    public function updateCancelled(Task $t): void
    {
        $this->updateCancelledCalls++;
        $this->store[$t->id] = $t;
    }
}

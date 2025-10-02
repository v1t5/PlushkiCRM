<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;

/**
 * Handles task FSM persistence. updateCompletedWithEvent persists the completion
 * + writes the task_completed outbox row in the same transaction.
 */
interface TaskRepo
{
    /** @throws \Plushki\Production\Domain\DomainException TaskNotFound */
    public function getById(string $id): Task;

    /** @return list<Task> */
    public function list(?string $planId, ?TaskStatus $status): array;

    public function updateStarted(Task $t): void;

    public function updateCompletedWithEvent(Task $t, OutboxEvent $evt): void;

    public function updateCancelled(Task $t): void;
}

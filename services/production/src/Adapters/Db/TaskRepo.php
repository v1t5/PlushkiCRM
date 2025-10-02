<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\TaskRepo as TaskRepoPort;

/**
 * DBAL task repository. updateCompletedWithEvent persists the completion and
 * writes the task_completed outbox row in one transaction.
 */
final class TaskRepo implements TaskRepoPort
{
    private const COLS = 'id, tenant_id, plan_id, product_id, qty, status, baker_id, started_at, completed_at, created_at, updated_at';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Task
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM tasks WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::TaskNotFound);
        }

        return self::map($row);
    }

    /** @return list<Task> */
    public function list(?string $planId, ?TaskStatus $status): array
    {
        $sql = 'SELECT ' . self::COLS . ' FROM tasks WHERE 1=1';
        $params = [];
        if ($planId !== null) {
            $sql .= ' AND plan_id = CAST(:plan_id AS uuid)';
            $params['plan_id'] = $planId;
        }
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status->value;
        }
        $sql .= ' ORDER BY created_at ASC';

        return array_map(self::map(...), $this->db->fetchAllAssociative($sql, $params));
    }

    public function updateStarted(Task $t): void
    {
        $this->db->executeStatement(
            "UPDATE tasks SET status = 'in_progress', baker_id = CAST(:baker AS uuid),
                started_at = CAST(:started AS timestamptz), updated_at = CAST(:updated AS timestamptz)
             WHERE id = CAST(:id AS uuid)",
            [
                'id' => $t->id,
                'baker' => $t->bakerId,
                'started' => $t->startedAt !== null ? Ts::fmt($t->startedAt) : null,
                'updated' => Ts::fmt($t->updatedAt),
            ],
        );
    }

    public function updateCompletedWithEvent(Task $t, OutboxEvent $evt): void
    {
        $this->db->transactional(function (Connection $tx) use ($t, $evt): void {
            $tx->executeStatement(
                "UPDATE tasks SET status = 'completed', completed_at = CAST(:completed AS timestamptz),
                    updated_at = CAST(:updated AS timestamptz)
                 WHERE id = CAST(:id AS uuid)",
                [
                    'id' => $t->id,
                    'completed' => $t->completedAt !== null ? Ts::fmt($t->completedAt) : null,
                    'updated' => Ts::fmt($t->updatedAt),
                ],
            );
            OutboxRepo::insertInto($tx, $evt);
        });
    }

    public function updateCancelled(Task $t): void
    {
        $this->db->executeStatement(
            "UPDATE tasks SET status = 'cancelled', updated_at = CAST(:updated AS timestamptz)
             WHERE id = CAST(:id AS uuid)",
            ['id' => $t->id, 'updated' => Ts::fmt($t->updatedAt)],
        );
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): Task
    {
        return new Task(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            planId: (string) $r['plan_id'],
            productId: (string) $r['product_id'],
            qty: (int) $r['qty'],
            status: TaskStatus::from((string) $r['status']),
            bakerId: $r['baker_id'] !== null ? (string) $r['baker_id'] : null,
            startedAt: $r['started_at'] !== null ? Ts::parse((string) $r['started_at']) : null,
            completedAt: $r['completed_at'] !== null ? Ts::parse((string) $r['completed_at']) : null,
            createdAt: Ts::parse((string) $r['created_at']),
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }
}

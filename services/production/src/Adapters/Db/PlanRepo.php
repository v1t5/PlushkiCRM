<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\PlanItem;
use Plushki\Production\Domain\PlanStatus;
use Plushki\Production\Domain\Task;
use Plushki\Production\Ports\OutboxEvent;
use Plushki\Production\Ports\PlanRepo as PlanRepoPort;

/**
 * DBAL plan repository. accumulateConfirmedLine and publishWithTasks each run as
 * a single transaction. plan_date is a DATE column (bound as Y-m-d text, cast to
 * date).
 */
final class PlanRepo implements PlanRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array{0: Plan, 1: list<PlanItem>} */
    public function getByDate(string $tenantId, \DateTimeImmutable $planDate): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, plan_date, status, published_at, created_at, updated_at
             FROM plans WHERE tenant_id = :tenant_id AND plan_date = CAST(:plan_date AS date)',
            ['tenant_id' => $tenantId, 'plan_date' => $planDate->format('Y-m-d')],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::PlanNotFound);
        }
        $plan = self::mapPlan($row);

        $itemRows = $this->db->fetchAllAssociative(
            'SELECT id, plan_id, product_id, qty, created_at, updated_at
             FROM plan_items WHERE plan_id = CAST(:plan_id AS uuid) ORDER BY created_at ASC',
            ['plan_id' => $plan->id],
        );

        return [$plan, array_map(self::mapItem(...), $itemRows)];
    }

    public function accumulateConfirmedLine(string $eventId, \DateTimeImmutable $planDate, string $productId, int $qty): void
    {
        $date = $planDate->format('Y-m-d');
        $this->db->transactional(function (Connection $tx) use ($eventId, $date, $productId, $qty): void {
            // Idempotency gate.
            $affected = $tx->executeStatement(
                'INSERT INTO applied_order_lines (event_id, product_id, qty, plan_date)
                 VALUES (CAST(:event_id AS uuid), CAST(:product_id AS uuid), CAST(:qty AS integer), CAST(:plan_date AS date))
                 ON CONFLICT (event_id, product_id) DO NOTHING',
                ['event_id' => $eventId, 'product_id' => $productId, 'qty' => $qty, 'plan_date' => $date],
            );
            if ($affected === 0) {
                return; // redelivery — already applied
            }

            // Upsert the draft plan.
            $now = Ts::fmt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $planRow = $tx->fetchAssociative(
                "INSERT INTO plans (id, tenant_id, plan_date, status, created_at, updated_at)
                 VALUES (CAST(:id AS uuid), :tenant_id, CAST(:plan_date AS date), 'draft', CAST(:now AS timestamptz), CAST(:now AS timestamptz))
                 ON CONFLICT (tenant_id, plan_date) DO UPDATE SET updated_at = EXCLUDED.updated_at
                 RETURNING id, tenant_id, plan_date, status, published_at, created_at, updated_at",
                ['id' => \Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'tenant_id' => 'default', 'plan_date' => $date, 'now' => $now],
            );
            /** @var array<string, mixed> $planRow */
            $plan = self::mapPlan($planRow);
            if ($plan->isPublished()) {
                throw DomainException::of(ErrorCode::PlanAlreadyPublished);
            }

            // Upsert the plan_items row (qty accumulates).
            $tx->executeStatement(
                'INSERT INTO plan_items (id, plan_id, product_id, qty, created_at, updated_at)
                 VALUES (CAST(:id AS uuid), CAST(:plan_id AS uuid), CAST(:product_id AS uuid), CAST(:qty AS integer), CAST(:now AS timestamptz), CAST(:now AS timestamptz))
                 ON CONFLICT (plan_id, product_id) DO UPDATE
                    SET qty = plan_items.qty + EXCLUDED.qty, updated_at = EXCLUDED.updated_at',
                ['id' => \Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'plan_id' => $plan->id, 'product_id' => $productId, 'qty' => $qty, 'now' => $now],
            );
        });
    }

    /**
     * @param list<Task> $tasks
     * @return array{0: Plan, 1: list<Task>}
     */
    public function publishWithTasks(Plan $plan, array $tasks, OutboxEvent $evt): array
    {
        $this->db->transactional(function (Connection $tx) use ($plan, $tasks, $evt): void {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $affected = $tx->executeStatement(
                "UPDATE plans SET status = 'published', published_at = CAST(:at AS timestamptz), updated_at = CAST(:at AS timestamptz)
                 WHERE id = CAST(:id AS uuid) AND status = 'draft'",
                ['id' => $plan->id, 'at' => Ts::fmt($now)],
            );
            if ($affected === 0) {
                throw DomainException::of(ErrorCode::PlanAlreadyPublished);
            }
            $plan->status = PlanStatus::Published;
            $plan->publishedAt = $now;
            $plan->updatedAt = $now;

            foreach ($tasks as $t) {
                $tx->executeStatement(
                    "INSERT INTO tasks (id, tenant_id, plan_id, product_id, qty, status, created_at, updated_at)
                     VALUES (CAST(:id AS uuid), :tenant_id, CAST(:plan_id AS uuid), CAST(:product_id AS uuid), CAST(:qty AS integer), 'open', CAST(:created AS timestamptz), CAST(:created AS timestamptz))",
                    [
                        'id' => $t->id,
                        'tenant_id' => $t->tenantId,
                        'plan_id' => $t->planId,
                        'product_id' => $t->productId,
                        'qty' => $t->qty,
                        'created' => Ts::fmt($t->createdAt),
                    ],
                );
            }
            OutboxRepo::insertInto($tx, $evt);
        });

        return [$plan, $tasks];
    }

    /** @param array<string, mixed> $r */
    private static function mapPlan(array $r): Plan
    {
        return new Plan(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            planDate: new \DateTimeImmutable((string) $r['plan_date'], new \DateTimeZone('UTC')),
            status: PlanStatus::from((string) $r['status']),
            publishedAt: $r['published_at'] !== null ? Ts::parse((string) $r['published_at']) : null,
            createdAt: Ts::parse((string) $r['created_at']),
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }

    /** @param array<string, mixed> $r */
    private static function mapItem(array $r): PlanItem
    {
        return new PlanItem(
            id: (string) $r['id'],
            planId: (string) $r['plan_id'],
            productId: (string) $r['product_id'],
            qty: (int) $r['qty'],
            createdAt: Ts::parse((string) $r['created_at']),
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }
}

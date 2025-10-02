<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * One production day, identified by (tenant_id, plan_date). planDate is a
 * calendar date (UTC midnight); status/publishedAt/updatedAt are mutable across
 * the publish step.
 */
final class Plan
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly \DateTimeImmutable $planDate,
        public PlanStatus $status,
        public ?\DateTimeImmutable $publishedAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(\DateTimeImmutable $planDate): self
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: 'default',
            planDate: $planDate,
            status: PlanStatus::Draft,
            publishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isPublished(): bool
    {
        return $this->status === PlanStatus::Published;
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * One (product, qty) unit of work generated at plan publish time. Status +
 * timestamps mutate through the FSM methods; the caller persists the result.
 * bakerId is opt-in.
 */
final class Task
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $planId,
        public readonly string $productId,
        public readonly int $qty,
        public TaskStatus $status,
        public ?string $bakerId,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $completedAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $planId, string $productId, int $qty): self
    {
        if ($qty <= 0) {
            throw DomainException::of(ErrorCode::InvalidQty);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: 'default',
            planId: $planId,
            productId: $productId,
            qty: $qty,
            status: TaskStatus::Open,
            bakerId: null,
            startedAt: null,
            completedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function start(?string $bakerId): void
    {
        $this->transitionGuard(TaskStatus::InProgress);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->status = TaskStatus::InProgress;
        $this->bakerId = $bakerId;
        $this->startedAt = $now;
        $this->updatedAt = $now;
    }

    public function complete(): void
    {
        $this->transitionGuard(TaskStatus::Completed);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->status = TaskStatus::Completed;
        $this->completedAt = $now;
        $this->updatedAt = $now;
    }

    public function cancel(): void
    {
        $this->transitionGuard(TaskStatus::Cancelled);
        $this->status = TaskStatus::Cancelled;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function transitionGuard(TaskStatus $to): void
    {
        if (!$this->status->canTransitionTo($to)) {
            throw DomainException::of(ErrorCode::InvalidTaskTransition);
        }
    }
}

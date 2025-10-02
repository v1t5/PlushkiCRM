<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Order is the aggregate root. Items are loaded by the repo when needed. Money
 * is int kopecks throughout.
 */
final class Order
{
    public const DEFAULT_TENANT = 'default';

    /** @param list<Item> $items */
    public function __construct(
        public string $id,
        public string $tenantId,
        public Channel $channel,
        public string $customerRef,
        public Status $status,
        public int $totalKopecks,
        public array $items,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Validates inputs and produces an Order in 'placed' status. Line numbers
     * are assigned 1..N preserving input order; total is the sum of per-line
     * subtotals.
     */
    public static function create(PlaceInput $in): self
    {
        if ($in->items === []) {
            throw DomainException::of(ErrorCode::EmptyOrder);
        }
        // Channel is already a valid enum, but re-parse to enforce the invariant.
        Channel::parse($in->channel->value);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $items = [];
        $total = 0;
        foreach (array_values($in->items) as $idx => $it) {
            if ($it->qty <= 0) {
                throw DomainException::of(ErrorCode::InvalidQuantity);
            }
            $it->lineNo = $idx + 1;
            $items[] = $it;
            $total += $it->subtotalKopecks();
        }

        return new self(
            id: Uuid::v7()->toRfc4122(),
            tenantId: self::DEFAULT_TENANT,
            channel: $in->channel,
            customerRef: trim($in->customerRef),
            status: Status::Placed,
            totalKopecks: $total,
            items: $items,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** Transition validates and applies a status change; caller persists. */
    public function transition(Status $to): void
    {
        if (!$this->status->canTransitionTo($to)) {
            throw DomainException::of(ErrorCode::InvalidTransition);
        }
        $this->status = $to;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

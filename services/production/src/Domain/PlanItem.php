<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

/**
 * A (product, qty) line on a plan. qty accumulates as orders confirm, until publish.
 */
final class PlanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public readonly string $productId,
        public readonly int $qty,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }
}

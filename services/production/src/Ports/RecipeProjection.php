<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

/**
 * The cached catalog recipe (BOM) for one product. Lines are snapshotted into
 * the task_completed event so inventory deducts without calling catalog.
 */
final class RecipeProjection
{
    /** @param list<RecipeLine> $lines */
    public function __construct(
        public string $productId,
        public string $tenantId,
        public string $productSku,
        public array $lines,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

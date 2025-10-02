<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

/**
 * The projected form of one recipe line, in the shape task_completed attaches.
 */
final class RecipeLine
{
    public function __construct(
        public string $ingredientId,
        public string $ingredientSku,
        public string $ingredientName,
        public int $qty,
        public string $unitId,
        public string $unitCode,
        public int $unitFactor,
        public int $qtyInBase,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Production\Ports;

/**
 * Upserts/reads the local catalog recipe cache. get returns null when the
 * product has no projected recipe yet.
 */
interface RecipeProjectionRepo
{
    public function upsert(RecipeProjection $p): void;

    public function get(string $productId): ?RecipeProjection;
}

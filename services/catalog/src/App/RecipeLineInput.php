<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

/**
 * The parsed-from-HTTP form of a single recipe line. ingredientId and unitId are
 * already-validated UUID strings (parsed at the HTTP boundary). A value object,
 * excluded from the service container.
 */
final class RecipeLineInput
{
    public function __construct(
        public readonly string $ingredientId,
        public readonly string $unitId,
        public readonly int $qty,
    ) {
    }
}

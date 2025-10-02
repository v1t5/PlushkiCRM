<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

/**
 * ItemKind tags whether a stock row belongs to an ingredient or a finished
 * product. Catalog owns the master records; inventory only holds the reference
 * UUID + the kind.
 */
enum ItemKind: string
{
    case Ingredient = 'ingredient';
    case Product = 'product';

    public static function isValid(string $raw): bool
    {
        return self::tryFrom($raw) !== null;
    }
}

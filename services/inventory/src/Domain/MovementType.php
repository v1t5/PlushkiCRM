<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

/**
 * MovementType is the categorical reason a stock_movement row exists. The sign
 * of qty_in_base is independent: IN / ADJUST-up positive, the rest negative;
 * ADJUST may go either way. Keep in sync with the CHECK constraint in
 * 0002_inventory.sql.
 */
enum MovementType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Waste = 'WASTE';
    case Adjust = 'ADJUST';
    case ConsumedByProduction = 'CONSUMED_BY_PRODUCTION';
    case Sold = 'SOLD';

    public static function isValid(string $raw): bool
    {
        return self::tryFrom($raw) !== null;
    }
}

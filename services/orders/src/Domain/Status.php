<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

/**
 * Status is the order lifecycle FSM. Allowed transitions live in
 * canTransitionTo; terminal states (cancelled, fulfilled) reject all moves.
 */
enum Status: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Fulfilled = 'fulfilled';

    /** Whether $raw is one of the four FSM states (HTTP query-filter boundary). */
    public static function isValid(string $raw): bool
    {
        return self::tryFrom($raw) !== null;
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Placed => $to === self::Confirmed || $to === self::Cancelled,
            self::Confirmed => $to === self::Fulfilled || $to === self::Cancelled,
            default => false,
        };
    }
}

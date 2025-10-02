<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

/**
 * Baker task FSM: open → in_progress → completed; open/in_progress → cancelled.
 * Terminal states (completed, cancelled) reject all moves.
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Open => $to === self::InProgress || $to === self::Cancelled,
            self::InProgress => $to === self::Completed || $to === self::Cancelled,
            default => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

/**
 * Channel marks where the order originated. Phase 1 only emits 'tg', but the
 * enum accepts the full v1 set so future channels do not require a migration.
 * Keep in sync with the CHECK constraint in 0002_orders.sql.
 */
enum Channel: string
{
    case TG = 'tg';
    case POS = 'pos';
    case Web = 'web';

    /** Trims + lowercases then matches; throws InvalidChannel. */
    public static function parse(string $s): self
    {
        return self::tryFrom(strtolower(trim($s)))
            ?? throw DomainException::of(ErrorCode::InvalidChannel);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * IdentityType is the kind of external handle attached to a Customer. pos_walkin
 * is a sentinel mapping every anonymous cafe sale within a tenant onto one
 * shared customer row.
 */
enum IdentityType: string
{
    case TG = 'tg';
    case Phone = 'phone';
    case Email = 'email';
    case PosWalkin = 'pos_walkin';

    public static function parse(string $s): self
    {
        return self::tryFrom($s) ?? throw DomainException::of(ErrorCode::InvalidIdentityType);
    }
}

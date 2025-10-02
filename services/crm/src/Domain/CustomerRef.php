<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * CustomerRef parses an orders customer_ref ("tg:42", "pos:walk-in") into an
 * (IdentityType, value) lookup pair. "pos:<marker>" maps to the per-tenant
 * shared walk-in identity (the marker is preserved as the value). Unknown prefix
 * or malformed ref → null (unattributed).
 */
final class CustomerRef
{
    public function __construct(
        public readonly IdentityType $type,
        public readonly string $value,
    ) {
    }

    public static function split(string $ref): ?self
    {
        $idx = strpos($ref, ':');
        if ($idx === false || $idx <= 0 || $idx === \strlen($ref) - 1) {
            return null;
        }
        $prefix = substr($ref, 0, $idx);
        $value = substr($ref, $idx + 1);

        return match ($prefix) {
            'tg' => new self(IdentityType::TG, $value),
            'phone' => new self(IdentityType::Phone, $value),
            'email' => new self(IdentityType::Email, $value),
            'pos' => new self(IdentityType::PosWalkin, $value),
            default => null,
        };
    }
}

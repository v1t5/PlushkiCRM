<?php

declare(strict_types=1);

namespace Plushki\Crm\App;

use Plushki\Crm\Domain\IdentityType;

/**
 * RegisterIdentity is one (type, value) pair in a registration request.
 */
final class RegisterIdentity
{
    public function __construct(
        public readonly IdentityType $type,
        public readonly string $value,
    ) {
    }
}

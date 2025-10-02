<?php

declare(strict_types=1);

namespace Plushki\Identity\Ports;

/**
 * UserListParams filters the admin user list. $q matches email or display_name
 * (case-insensitive substring). $includeArchived toggles whether soft-deleted
 * users appear. $limit is clamped server-side.
 */
final class UserListParams
{
    public function __construct(
        public string $tenantId = 'default',
        public string $q = '',
        public bool $includeArchived = false,
        public int $limit = 0,
        public int $offset = 0,
    ) {
    }
}

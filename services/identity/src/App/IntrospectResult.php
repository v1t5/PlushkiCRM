<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

/**
 * IntrospectResult is what the gateway gets back. actorId is the service-token
 * ID, not a user — gateway middleware stamps it into X-Actor-Id.
 */
final class IntrospectResult
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $actorType,
        public string $actorId,
        public string $tenantId,
        public array $scopes,
    ) {
    }
}

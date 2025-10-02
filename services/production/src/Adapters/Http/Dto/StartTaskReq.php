<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Http\Dto;

/**
 * POST /v1/tasks/{id}/start body. baker_id is optional; the controller validates
 * it as a UUID when present.
 */
final class StartTaskReq
{
    public ?string $baker_id = null;
}

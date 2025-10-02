<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

/**
 * Plan lifecycle. A plan starts 'draft' as orders.v1.confirmed events
 * accumulate; flips to 'published' on POST /v1/plans/{date}/publish.
 */
enum PlanStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

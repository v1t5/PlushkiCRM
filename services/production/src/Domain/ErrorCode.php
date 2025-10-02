<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

/**
 * Enumerates the domain errors production surfaces. The HTTP adapter maps each
 * to RFC 7807.
 */
enum ErrorCode: string
{
    case InvalidQty = 'invalid qty';
    case InvalidDate = 'invalid date';
    case InvalidProductRef = 'invalid product reference';
    case PlanNotFound = 'plan not found';
    case TaskNotFound = 'task not found';
    case PlanAlreadyPublished = 'plan already published';
    case PlanNotPublished = 'plan not published';
    case PlanEmpty = 'plan has no items';
    case InvalidTaskTransition = 'invalid task transition';
}

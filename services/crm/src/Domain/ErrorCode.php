<?php

declare(strict_types=1);

namespace Plushki\Crm\Domain;

/**
 * ErrorCode enumerates the domain errors crm surfaces. The HTTP adapter maps
 * each to RFC 7807.
 */
enum ErrorCode: string
{
    case CustomerNotFound = 'customer not found';
    case IdentityNotFound = 'identity not found';
    case InvalidIdentityType = 'invalid identity type';
    case IdentityValueRequired = 'identity value is required';
    case IdentityConflict = 'identity already bound to another customer';
}

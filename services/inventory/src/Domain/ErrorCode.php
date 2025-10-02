<?php

declare(strict_types=1);

namespace Plushki\Inventory\Domain;

/**
 * ErrorCode enumerates the domain errors inventory surfaces. The HTTP adapter
 * maps each code to an RFC 7807 type URI.
 */
enum ErrorCode: string
{
    case InvalidName = 'invalid name';
    case InvalidCode = 'invalid code';
    case InvalidQty = 'invalid qty';
    case InvalidItemKind = 'invalid item kind';
    case InvalidMovementType = 'invalid movement type';
    case InvalidWarehouseRef = 'invalid warehouse reference';
    case InvalidItemRef = 'invalid item reference';
    case CodeAlreadyTaken = 'code already taken';
    case WarehouseNotFound = 'warehouse not found';
    case WarehouseArchived = 'warehouse archived';
    case InsufficientStock = 'insufficient stock';
}

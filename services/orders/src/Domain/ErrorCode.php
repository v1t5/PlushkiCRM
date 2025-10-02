<?php

declare(strict_types=1);

namespace Plushki\Orders\Domain;

/**
 * ErrorCode enumerates the domain errors orders surfaces to the app layer. The
 * HTTP adapter maps each code to an RFC 7807 type URI.
 */
enum ErrorCode: string
{
    case InvalidChannel = 'invalid channel';
    case InvalidQuantity = 'invalid quantity';
    case EmptyOrder = 'order has no items';
    case OrderNotFound = 'order not found';
    case ProductNotFound = 'product not found';
    case InvalidTransition = 'invalid status transition';
    case CatalogUnavailable = 'catalog unavailable';
}

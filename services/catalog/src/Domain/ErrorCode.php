<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

/**
 * Enumerates the domain errors catalog surfaces to the app layer. Each `value`
 * is the human-readable string used as the problem+json `detail`. The HTTP
 * adapter (CatalogExceptionSubscriber) maps each code to an RFC 7807 type URI.
 */
enum ErrorCode: string
{
    case InvalidName = 'invalid name';
    case InvalidSlug = 'invalid slug';
    case InvalidSKU = 'invalid sku';
    case InvalidPrice = 'invalid price';
    case InvalidUnitCode = 'invalid unit code';
    case InvalidUnitFactor = 'invalid unit factor';
    case InvalidUnitRef = 'invalid unit reference';
    case InvalidThreshold = 'invalid threshold';
    case InvalidProductRef = 'invalid product reference';
    case InvalidIngredientRef = 'invalid ingredient reference';
    case InvalidQty = 'invalid qty';
    case SlugAlreadyTaken = 'slug already taken';
    case SKUAlreadyTaken = 'sku already taken';
    case CodeAlreadyTaken = 'code already taken';
    case CategoryNotFound = 'category not found';
    case ProductNotFound = 'product not found';
    case IngredientNotFound = 'ingredient not found';
    case UnitNotFound = 'unit not found';
    case CategoryArchived = 'category archived';
    case ProductArchived = 'product archived';
    case IngredientArchived = 'ingredient archived';
    case UnitArchived = 'unit archived';
    case BaseUnitMustBeBase = 'base_unit_id must reference a base unit';
    case DuplicateRecipeLine = 'duplicate ingredient on recipe';
}
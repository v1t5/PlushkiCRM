<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Platform\Problem;

/**
 * Maps domain errors to RFC 7807 problem+json with catalog's stable type URIs.
 * Runs before the generic Platform\Http\ProblemSubscriber and only handles
 * DomainException; the explicit ProblemException thrown by controllers falls
 * through to the generic handler.
 */
final class CatalogExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/catalog/';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 128]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof DomainException) {
            return;
        }

        $event->setResponse(self::problemFor($e->errorCode)->toResponse());
    }

    public static function problemFor(ErrorCode $code): Problem
    {
        [$suffix, $title, $status, $detail] = match ($code) {
            ErrorCode::InvalidName,
            ErrorCode::InvalidSlug,
            ErrorCode::InvalidSKU,
            ErrorCode::InvalidPrice,
            ErrorCode::InvalidUnitCode,
            ErrorCode::InvalidUnitFactor,
            ErrorCode::InvalidUnitRef,
            ErrorCode::InvalidThreshold,
            ErrorCode::InvalidProductRef,
            ErrorCode::InvalidIngredientRef,
            ErrorCode::InvalidQty,
            ErrorCode::BaseUnitMustBeBase,
            ErrorCode::DuplicateRecipeLine => ['validation-failed', 'Validation Failed', 400, $code->value],
            ErrorCode::SlugAlreadyTaken => ['slug-taken', 'Slug Already Taken', 409, ''],
            ErrorCode::SKUAlreadyTaken => ['sku-taken', 'SKU Already Taken', 409, ''],
            ErrorCode::CodeAlreadyTaken => ['code-taken', 'Code Already Taken', 409, ''],
            ErrorCode::CategoryNotFound,
            ErrorCode::ProductNotFound,
            ErrorCode::IngredientNotFound,
            ErrorCode::UnitNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::CategoryArchived,
            ErrorCode::ProductArchived,
            ErrorCode::IngredientArchived,
            ErrorCode::UnitArchived => ['archived', 'Resource Archived', 410, ''],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}
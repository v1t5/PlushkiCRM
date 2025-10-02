<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Platform\Problem;

/**
 * OrdersExceptionSubscriber maps domain errors to RFC 7807 problem+json with
 * orders' stable type URIs. Runs before the generic Platform\Http\ProblemSubscriber;
 * only handles DomainException (the explicit ProblemException thrown by controllers
 * falls through to the generic handler).
 */
final class OrdersExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/orders/';

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
            ErrorCode::InvalidChannel,
            ErrorCode::InvalidQuantity,
            ErrorCode::EmptyOrder => ['validation-failed', 'Validation Failed', 400, $code->value],
            ErrorCode::OrderNotFound,
            ErrorCode::ProductNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::InvalidTransition => ['invalid-transition', 'Invalid Status Transition', 409, ''],
            ErrorCode::CatalogUnavailable => ['catalog-unavailable', 'Catalog Unavailable', 502, ''],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}
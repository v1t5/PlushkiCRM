<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Platform\Problem;

/**
 * Maps domain errors to RFC 7807 with production's stable type URIs.
 */
final class ProductionExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/production/';

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
            ErrorCode::InvalidQty,
            ErrorCode::InvalidDate,
            ErrorCode::InvalidProductRef,
            ErrorCode::PlanEmpty => ['validation-failed', 'Validation Failed', 400, $code->value],
            ErrorCode::PlanNotFound,
            ErrorCode::TaskNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::PlanAlreadyPublished,
            ErrorCode::PlanNotPublished,
            ErrorCode::InvalidTaskTransition => ['state-conflict', 'State Conflict', 409, $code->value],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}

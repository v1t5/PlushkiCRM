<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Platform\Problem;

/**
 * CrmExceptionSubscriber maps domain errors to RFC 7807 with crm's stable type
 * URIs.
 */
final class CrmExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/crm/';

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
            ErrorCode::CustomerNotFound,
            ErrorCode::IdentityNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::InvalidIdentityType,
            ErrorCode::IdentityValueRequired => ['validation-failed', 'Validation Failed', 400, $code->value],
            ErrorCode::IdentityConflict => ['identity-conflict', 'Identity Conflict', 409, $code->value],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Platform\Problem;

/**
 * IdentityExceptionSubscriber maps domain errors to RFC 7807 problem+json with
 * identity's stable type URIs. It runs before the generic
 * Platform\Http\ProblemSubscriber and only handles DomainException; everything
 * else (including the explicit ProblemException thrown by controllers) falls
 * through to the generic handler.
 */
final class IdentityExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/identity/';

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
            ErrorCode::InvalidCredentials,
            ErrorCode::UserArchived => ['invalid-credentials', 'Invalid Credentials', 401, ''],
            ErrorCode::EmailAlreadyTaken => ['email-taken', 'Email Already Taken', 409, ''],
            ErrorCode::InvalidEmail => ['invalid-email', 'Invalid Email', 400, ''],
            ErrorCode::PasswordTooShort => ['password-too-short', 'Password Too Short', 400, ''],
            ErrorCode::RefreshTokenInvalid,
            ErrorCode::RefreshTokenExpired,
            ErrorCode::RefreshTokenUsed => ['refresh-invalid', 'Refresh Token Invalid', 401, $code->value],
            ErrorCode::UserNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::InvalidRole => ['invalid-role', 'Invalid Role', 400, ''],
            ErrorCode::ServiceTokenInvalid,
            ErrorCode::ServiceTokenRevoked => ['service-token-invalid', 'Service Token Invalid', 401, ''],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Inventory\Domain\DomainException;
use Plushki\Inventory\Domain\ErrorCode;
use Plushki\Inventory\Platform\Problem;

/**
 * InventoryExceptionSubscriber maps domain errors to RFC 7807 with inventory's
 * stable type URIs. Runs before the generic Platform\Http\ProblemSubscriber.
 */
final class InventoryExceptionSubscriber implements EventSubscriberInterface
{
    public const ERROR_BASE = 'https://errors.plushki/inventory/';

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
            ErrorCode::InvalidCode,
            ErrorCode::InvalidQty,
            ErrorCode::InvalidItemKind,
            ErrorCode::InvalidMovementType,
            ErrorCode::InvalidWarehouseRef,
            ErrorCode::InvalidItemRef => ['validation-failed', 'Validation Failed', 400, $code->value],
            ErrorCode::CodeAlreadyTaken => ['code-taken', 'Code Already Taken', 409, ''],
            ErrorCode::WarehouseNotFound => ['not-found', 'Not Found', 404, ''],
            ErrorCode::WarehouseArchived => ['archived', 'Resource Archived', 410, ''],
            ErrorCode::InsufficientStock => ['insufficient-stock', 'Insufficient Stock', 409, ''],
        };

        return Problem::new(self::ERROR_BASE . $suffix, $title, $status, $detail);
    }
}

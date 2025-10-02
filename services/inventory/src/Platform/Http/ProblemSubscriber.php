<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Inventory\Platform\Problem;
use Plushki\Inventory\Platform\ProblemException;

/**
 * ProblemSubscriber renders every uncaught error as RFC 7807 problem+json. A
 * ProblemException carries an explicit Problem; framework 404/405 map to the
 * common type URIs; anything else becomes a 500 internal-error problem.
 */
final class ProblemSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priority high enough to win over Symfony's default error rendering.
        return [KernelEvents::EXCEPTION => ['onException', 64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $path = $event->getRequest()->getPathInfo();

        $problem = match (true) {
            $e instanceof ProblemException => $e->problem,
            $e instanceof NotFoundHttpException => Problem::notFound($path),
            $e instanceof MethodNotAllowedHttpException => Problem::methodNotAllowed(
                $event->getRequest()->getMethod() . ' ' . $path
            ),
            default => Problem::new(
                'https://errors.plushki/common/internal',
                'Internal Server Error',
                500,
            ),
        };

        $event->setResponse($problem->toResponse());
    }
}

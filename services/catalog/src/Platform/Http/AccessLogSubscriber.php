<?php

declare(strict_types=1);

namespace Plushki\Catalog\Platform\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits one structured log line per HTTP request with status, duration and
 * route. trace_id/span_id are added by the Monolog formatter automatically
 * (see Platform\Log).
 */
final class AccessLogSubscriber implements EventSubscriberInterface
{
    private float $start = 0.0;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 4096],
            KernelEvents::RESPONSE => ['onResponse', -4096],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->start = microtime(true);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $req = $event->getRequest();
        $resp = $event->getResponse();
        $this->logger->info('http request', [
            'method' => $req->getMethod(),
            'path' => $req->getPathInfo(),
            'status' => $resp->getStatusCode(),
            'bytes' => strlen((string) $resp->getContent()),
            'duration_ms' => round((microtime(true) - $this->start) * 1000, 3),
            'remote' => $req->getClientIp() ?? '',
        ]);
    }
}

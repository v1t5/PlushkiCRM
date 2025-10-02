<?php

declare(strict_types=1);

namespace Plushki\Orders\Platform\Http;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * TraceRequestSubscriber opens one server span per HTTP request, extracting an
 * inbound W3C traceparent so traces stitch across the gateway and other
 * services. The active span lets Platform\Log attach trace_id/span_id to every
 * log line.
 */
final class TraceRequestSubscriber implements EventSubscriberInterface
{
    private array $spans = [];
    private array $scopes = [];

    public function __construct(private readonly string $service)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 8192],
            KernelEvents::TERMINATE => ['onTerminate', -8192],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $req = $event->getRequest();

        $carrier = [];
        foreach (['traceparent', 'tracestate'] as $h) {
            if ($req->headers->has($h)) {
                $carrier[$h] = $req->headers->get($h);
            }
        }
        $parent = Globals::propagator()->extract($carrier);

        $tracer = Globals::tracerProvider()->getTracer($this->service);
        $span = $tracer->spanBuilder($req->getMethod() . ' ' . $req->getPathInfo())
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $id = spl_object_id($req);
        $this->scopes[$id] = $span->storeInContext($parent)->activate();
        $this->spans[$id] = $span;
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $id = spl_object_id($event->getRequest());
        $span = $this->spans[$id] ?? null;
        $scope = $this->scopes[$id] ?? null;
        if ($span !== null) {
            $span->setAttribute('http.response.status_code', $event->getResponse()->getStatusCode());
            $span->end();
        }
        if ($scope instanceof ScopeInterface) {
            $scope->detach();
        }
        unset($this->spans[$id], $this->scopes[$id]);
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Notifications\Platform;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Wires the global OpenTelemetry tracer provider with an OTLP/HTTP exporter
 * pointed at Tempo's HTTP receiver (http://tempo:4318), avoiding the ext-grpc
 * requirement.
 *
 * Init is best-effort: if the OTLP stack cannot start, the service still boots
 * with a no-op tracer and a logged warning, so observability problems never
 * take the service down.
 */
final class Otel
{
    private static ?TracerProvider $provider = null;

    public static function init(string $service, string $endpoint): void
    {
        $endpoint = self::normaliseEndpoint($endpoint);
        try {
            $transport = (new OtlpHttpTransportFactory())
                ->create($endpoint . '/v1/traces', 'application/x-protobuf');
            $exporter = new SpanExporter($transport);

            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(Attributes::create([
                    ResourceAttributes::SERVICE_NAME => $service,
                ]))
            );

            $provider = TracerProvider::builder()
                ->addSpanProcessor(new SimpleSpanProcessor($exporter))
                ->setResource($resource)
                ->setSampler(new ParentBased(new AlwaysOnSampler()))
                ->build();

            Sdk::builder()
                ->setTracerProvider($provider)
                ->setPropagator(TraceContextPropagator::getInstance())
                ->setAutoShutdown(true)
                ->buildAndRegisterGlobal();

            self::$provider = $provider;
        } catch (\Throwable $e) {
            error_log('otel init failed, continuing without tracing: ' . $e->getMessage());
        }
    }

    /** Returns the global tracer (a no-op tracer if init failed). */
    public static function tracer(string $service): TracerInterface
    {
        return Globals::tracerProvider()->getTracer($service);
    }

    public static function shutdown(): void
    {
        self::$provider?->shutdown();
    }

    private static function normaliseEndpoint(string $s): string
    {
        // Accept either a bare host:port or a full http(s)://host:port.
        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) {
            return rtrim($s, '/');
        }

        return 'http://' . rtrim($s, '/');
    }
}

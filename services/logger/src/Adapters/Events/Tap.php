<?php

declare(strict_types=1);

namespace Plushki\Logger\Adapters\Events;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Declares one exclusive, auto-delete (server-named) queue, binds it with routing
 * key `#` to every per-context topic exchange, and logs each delivery. The
 * envelope trace_id is turned back into a remote SpanContext + a
 * `consume.<routing_key>` span so Grafana shows one trace spanning
 * HTTP → outbox publish → consume → log line.
 *
 * Durability is unnecessary: the queue dies with the process; on restart we
 * resume from "now". RabbitMQ has no cross-exchange wildcard, so the taps list
 * enumerates the exchanges — adding a bounded context means appending here.
 */
final class Tap
{
    /** @var list<string> */
    private const TAPS = ['CATALOG', 'ORDERS', 'INVENTORY', 'PRODUCTION', 'CRM', 'IDENTITY'];

    private bool $stop = false;

    public function __construct(
        private readonly AMQPStreamConnection $conn,
        private readonly string $service,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        $this->installSignalHandlers();

        $ch = $this->conn->channel();
        // Server-named, non-durable, exclusive, auto-delete queue: each logger
        // replica taps independently rather than competing for messages.
        [$queue] = $ch->queue_declare('', false, false, true, true);

        foreach (self::TAPS as $exch) {
            $ch->exchange_declare($exch, 'topic', false, true, false);
            $ch->queue_bind($queue, $exch, '#');
        }

        $ch->basic_consume(
            $queue,
            consumer_tag: $this->service,
            no_local: false,
            no_ack: false,
            exclusive: true,
            nowait: false,
            callback: fn (AMQPMessage $msg) => $this->handle($msg),
        );

        $this->logger->info('subscribed', ['binding' => '#', 'exchanges' => implode(',', self::TAPS)]);

        while (!$this->stop && $ch->is_consuming()) {
            try {
                $ch->wait(null, false, 5);
            } catch (AMQPTimeoutException) {
                // idle tick — loop and re-check stop flag
            }
        }

        $ch->close();
        $this->logger->info('tap stopped');
    }

    private function handle(AMQPMessage $msg): void
    {
        $env = [];
        try {
            $decoded = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($decoded)) {
                $env = $decoded;
            }
        } catch (\JsonException) {
            // best-effort: non-JSON bodies still produce a log line
        }

        $routingKey = $msg->getRoutingKey();
        $span = $this->startConsumeSpan($routingKey, (string) ($env['trace_id'] ?? ''));
        $scope = $span->activate();
        try {
            $this->logger->info('event received', [
                'exchange' => $msg->getExchange(),
                'routing_key' => $routingKey,
                'schema' => (string) ($env['schema'] ?? ''),
                'event_id' => (string) ($env['event_id'] ?? ''),
                'size' => \strlen($msg->getBody()),
            ]);
            $msg->ack();
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function startConsumeSpan(string $routingKey, string $traceId): \OpenTelemetry\API\Trace\SpanInterface
    {
        $tracer = Globals::tracerProvider()->getTracer($this->service);
        $parent = Context::getCurrent();
        if ($traceId !== '' && ctype_xdigit($traceId) && \strlen($traceId) === 32) {
            $sc = SpanContext::createFromRemoteParent($traceId, str_repeat('0', 16), TraceFlags::SAMPLED);
            if ($sc instanceof SpanContextInterface && $sc->isValid()) {
                $parent = \OpenTelemetry\API\Trace\Span::wrap($sc)->storeInContext(Context::getCurrent());
            }
        }

        return $tracer->spanBuilder('consume.' . $routingKey)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->startSpan();
    }

    private function installSignalHandlers(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $handler = function (): void {
            $this->stop = true;
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}

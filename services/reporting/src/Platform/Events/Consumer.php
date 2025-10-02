<?php

declare(strict_types=1);

namespace Plushki\Reporting\Platform\Events;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Subscribes a durable queue to a topic exchange and dispatches each message to
 * a handler:
 *
 *   - durable, non-exclusive queue (e.g. reporting-orders-fulfilled),
 *   - bound with a topic pattern like `orders.v1.fulfilled.#`,
 *   - prefetch QoS, manual ack after the handler commits,
 *   - parse error / PoisonException -> Nack(requeue=false),
 *   - any other handler error -> Nack(requeue=true).
 *
 * The envelope trace_id is turned back into a remote SpanContext and a
 * `consume.<routing_key>` span is opened, so traces stitch across services.
 * One container per consumer (`<svc>-consume-<src>`).
 *
 * @phpstan-type Handler callable(Envelope): void
 */
final class Consumer
{
    private bool $stop = false;

    public function __construct(
        private readonly AMQPStreamConnection $conn,
        private readonly string $service,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable(Envelope): void $handler
     */
    public function run(
        string $exchange,
        string $queue,
        string $bindingKey,
        callable $handler,
        int $prefetch = 64,
    ): void {
        $this->installSignalHandlers();

        $ch = $this->conn->channel();
        $ch->exchange_declare($exchange, 'topic', false, true, false);
        $ch->queue_declare($queue, false, true, false, false);
        $ch->queue_bind($queue, $exchange, $bindingKey);
        $ch->basic_qos(0, $prefetch, false);

        $ch->basic_consume(
            $queue,
            consumer_tag: $this->service . '-' . $queue,
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: fn (AMQPMessage $msg) => $this->dispatch($msg, $handler),
        );

        $this->logger->info('consumer started', [
            'exchange' => $exchange,
            'queue' => $queue,
            'binding' => $bindingKey,
        ]);

        while (!$this->stop && $ch->is_consuming()) {
            try {
                $ch->wait(null, false, 5);
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {
                // idle tick — loop and re-check stop flag
            }
        }

        $ch->close();
        $this->logger->info('consumer stopped', ['queue' => $queue]);
    }

    /**
     * @param callable(Envelope): void $handler
     */
    private function dispatch(AMQPMessage $msg, callable $handler): void
    {
        $routingKey = $msg->getRoutingKey();
        try {
            $env = Envelope::fromJson($msg->getBody());
        } catch (\Throwable $e) {
            $this->logger->warning('drop unparsable message', [
                'routing_key' => $routingKey,
                'err' => $e->getMessage(),
            ]);
            $msg->nack(false); // requeue=false -> drop
            return;
        }

        $span = $this->startConsumeSpan($routingKey, $env);
        $scope = $span->activate();
        try {
            $handler($env);
            $msg->ack();
        } catch (PoisonException $e) {
            $this->logger->warning('poison message dropped', [
                'routing_key' => $routingKey,
                'event_id' => $env->eventId,
                'err' => $e->getMessage(),
            ]);
            $msg->nack(false); // drop
        } catch (\Throwable $e) {
            $this->logger->warning('handler error, requeue', [
                'routing_key' => $routingKey,
                'event_id' => $env->eventId,
                'err' => $e->getMessage(),
            ]);
            $msg->nack(true); // requeue
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function startConsumeSpan(string $routingKey, Envelope $env): \OpenTelemetry\API\Trace\SpanInterface
    {
        $tracer = Globals::tracerProvider()->getTracer($this->service);
        $parent = Context::getCurrent();
        if ($env->traceId !== '' && ctype_xdigit($env->traceId) && \strlen($env->traceId) === 32) {
            $sc = SpanContext::createFromRemoteParent($env->traceId, str_repeat('0', 16), TraceFlags::SAMPLED);
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

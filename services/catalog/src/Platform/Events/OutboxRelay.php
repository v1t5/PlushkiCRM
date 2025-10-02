<?php

declare(strict_types=1);

namespace Plushki\Catalog\Platform\Events;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Drains outbox_events into a service's topic exchange on RabbitMQ. Pull cadence
 * is $intervalMs, batch size is $batchSize. On publish failure the event stays
 * unpublished and is retried on the next tick — consumers dedupe on event_id at
 * the DB layer, so redelivery is safe.
 *
 * The routing key is `<schema>.<tenant_id>` (e.g. identity.v1.user_created.default).
 *
 * Runs as a long-running console worker process (one container per service:
 * `<svc>-relay`).
 */
final class OutboxRelay
{
    private bool $stop = false;

    public function __construct(
        private readonly OutboxStore $store,
        private readonly AMQPStreamConnection $conn,
        private readonly string $exchange,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(int $intervalMs = 500, int $batchSize = 100): void
    {
        $this->installSignalHandlers();

        $ch = $this->conn->channel();
        // durable topic exchange, not auto-deleted.
        $ch->exchange_declare($this->exchange, 'topic', false, true, false);

        $this->logger->info('outbox relay started', [
            'exchange' => $this->exchange,
            'interval_ms' => $intervalMs,
            'batch' => $batchSize,
        ]);

        while (!$this->stop) {
            try {
                $this->drain($ch, $batchSize);
            } catch (\Throwable $e) {
                $this->logger->warning('outbox drain', ['err' => $e->getMessage()]);
            }
            $this->sleepMs($intervalMs);
        }

        $ch->close();
        $this->logger->info('outbox relay stopped');
    }

    private function drain(\PhpAmqpLib\Channel\AMQPChannel $ch, int $batchSize): void
    {
        $events = $this->store->fetchUnpublished($batchSize);
        if ($events === []) {
            return;
        }

        $published = [];
        foreach ($events as $e) {
            $routingKey = $e->schema . '.' . $e->tenantId;
            try {
                $msg = new AMQPMessage($e->payload, [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $e->eventId,
                    'timestamp' => time(),
                ]);
                $ch->basic_publish($msg, $this->exchange, $routingKey);
                $published[] = $e->eventId;
            } catch (\Throwable $err) {
                $this->logger->warning('publish', [
                    'event_id' => $e->eventId,
                    'routing_key' => $routingKey,
                    'err' => $err->getMessage(),
                ]);
            }
        }

        if ($published !== []) {
            $this->store->markPublished($published, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->logger->info('outbox drained', [
                'count' => \count($published),
                'pending' => \count($events) - \count($published),
            ]);
        }
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

    private function sleepMs(int $ms): void
    {
        usleep($ms * 1000);
    }
}

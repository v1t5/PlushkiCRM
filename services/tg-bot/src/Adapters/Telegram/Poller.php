<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Telegram;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use Psr\Log\LoggerInterface;

/**
 * Poller is a long-poll loop over getUpdates. Offset is in-memory; on restart
 * we start at 0, so Telegram redelivers anything from the last 24h not acked
 * via offset (acceptable for Phase 1). Each update opens a server-kind
 * `tg.update` span so handler-issued HTTP calls to catalog/orders stitch into
 * one trace. The loop runs in the `tg-bot-poll` console-worker process; the
 * HTTP container only serves health/metrics.
 */
final class Poller
{
    private bool $stop = false;
    private int $offset = 0;

    public function __construct(
        private readonly Api $api,
        private readonly LoggerInterface $logger,
        private readonly int $pollTimeout = 30,
    ) {
    }

    /**
     * Drives the loop until a SIGTERM/SIGINT flips the stop flag. Errors are
     * logged and the loop backs off ~2s so a flapping network doesn't burn CPU.
     *
     * @param callable(Update): void $onUpdate
     */
    public function run(callable $onUpdate): void
    {
        $this->installSignalHandlers();
        $timeout = $this->pollTimeout > 0 ? $this->pollTimeout : 30;
        $this->logger->info('poller starting', ['poll_timeout_s' => $timeout]);

        $tracer = Globals::tracerProvider()->getTracer('tg-bot');

        while (!$this->stop) {
            try {
                $updates = $this->api->getUpdates($this->offset, $timeout);
            } catch (\Throwable $e) {
                if ($this->stop) {
                    break;
                }
                $this->logger->warning('getUpdates', ['err' => $e->getMessage()]);
                $this->sleep(2);
                continue;
            }

            foreach ($updates as $u) {
                if ($u->updateId >= $this->offset) {
                    $this->offset = $u->updateId + 1;
                }
                $span = $tracer->spanBuilder('tg.update')->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
                $chatId = $u->message?->chat->id ?? $u->callbackQuery?->message?->chat->id;
                if ($chatId !== null) {
                    $span->setAttribute('tg.chat_id', $chatId);
                }
                $scope = $span->activate();
                try {
                    $onUpdate($u);
                } catch (\Throwable $e) {
                    $this->logger->warning('handle update', ['err' => $e->getMessage()]);
                } finally {
                    $scope->detach();
                    $span->end();
                }
            }
        }

        $this->logger->info('poller stopped');
    }

    /** Interruptible sleep: bails early when a stop signal arrives. */
    private function sleep(int $seconds): void
    {
        for ($i = 0; $i < $seconds && !$this->stop; $i++) {
            sleep(1);
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
}

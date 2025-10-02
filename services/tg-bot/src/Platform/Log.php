<?php

declare(strict_types=1);

namespace Plushki\TgBot\Platform;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use Psr\Log\LoggerInterface;

/**
 * Log builds a JSON Monolog logger that writes to stdout with the standard
 * fields (svc, env) and decorates every line with trace_id / span_id from the
 * active OTel span, so Loki/Grafana queries work uniformly across services.
 */
final class Log
{
    public static function new(string $service, string $env, string $levelStr): LoggerInterface
    {
        $handler = new StreamHandler('php://stdout', self::parseLevel($levelStr));
        $handler->setFormatter(self::formatter($service, $env));

        $logger = new Logger($service);
        $logger->pushHandler($handler);

        return $logger;
    }

    private static function parseLevel(string $s): Level
    {
        return match (strtolower($s)) {
            'debug' => Level::Debug,
            'warn', 'warning' => Level::Warning,
            'error' => Level::Error,
            default => Level::Info,
        };
    }

    private static function formatter(string $service, string $env): FormatterInterface
    {
        return new class($service, $env) implements FormatterInterface {
            public function __construct(
                private readonly string $service,
                private readonly string $env,
            ) {
            }

            public function format(LogRecord $record): string
            {
                $line = [
                    'time' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
                    'level' => strtoupper($record->level->getName()),
                    'svc' => $this->service,
                    'env' => $this->env,
                ];

                $span = Span::getCurrent()->getContext();
                if ($span->isValid()) {
                    $line['trace_id'] = $span->getTraceId();
                    $line['span_id'] = $span->getSpanId();
                }

                $line['msg'] = $record->message;
                foreach ($record->context as $k => $v) {
                    $line[$k] = $v;
                }

                return json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            }

            /** @param array<LogRecord> $records */
            public function formatBatch(array $records): string
            {
                return implode('', array_map($this->format(...), $records));
            }
        };
    }
}

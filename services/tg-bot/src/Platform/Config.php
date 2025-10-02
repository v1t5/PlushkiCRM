<?php

declare(strict_types=1);

namespace Plushki\TgBot\Platform;

/**
 * Config is the resolved runtime configuration for the service.
 *
 * Defaults come from the constructor; they are overlaid first by an optional
 * config.yaml (so a developer can pin local values), then by APP_* environment
 * variables (so containerized deployments stay 12-factor). Env wins.
 *
 * Each service owns its own copy of this class and may add service-specific
 * fields (e.g. identity's JWT settings) — drift is allowed.
 */
final class Config
{
    // tg-bot is stateless — no databaseUrl, no amqpUrl. It speaks HTTP to
    // catalog/orders and long-polls Telegram.
    public function __construct(
        public readonly string $service = 'tg-bot',
        public readonly string $env = 'dev',
        public readonly string $httpAddr = ':8080',
        public readonly string $otlpEndpoint = 'http://tempo:4318',
        public readonly string $logLevel = 'info',
    ) {
    }

    /**
     * Resolves config from defaults -> optional config.yaml -> APP_* env.
     * The yaml path is optional: a missing file is not an error.
     */
    public static function load(?string $configYamlPath = null): self
    {
        $d = new self();
        $values = [
            'service' => $d->service,
            'env' => $d->env,
            'http_addr' => $d->httpAddr,
            'otlp_endpoint' => $d->otlpEndpoint,
            'log_level' => $d->logLevel,
        ];

        $yaml = $configYamlPath ?? \dirname(__DIR__, 2) . '/config.yaml';
        if (is_file($yaml)) {
            /** @var array<string, mixed> $parsed */
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($yaml) ?? [];
            foreach ($parsed as $k => $v) {
                if (\array_key_exists($k, $values) && $v !== null) {
                    $values[$k] = (string) $v;
                }
            }
        }

        foreach (array_keys($values) as $k) {
            $env = self::env('APP_' . strtoupper($k));
            if ($env !== null) {
                $values[$k] = $env;
            }
        }

        return new self(
            service: $values['service'],
            env: $values['env'],
            httpAddr: $values['http_addr'],
            otlpEndpoint: $values['otlp_endpoint'],
            logLevel: $values['log_level'],
        );
    }

    /** Read an environment variable, returning null when unset/empty. */
    public static function env(string $name): ?string
    {
        $v = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($v === false || $v === null || $v === '') {
            return null;
        }

        return (string) $v;
    }
}

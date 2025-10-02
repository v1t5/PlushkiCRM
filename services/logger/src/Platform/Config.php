<?php

declare(strict_types=1);

namespace Plushki\Logger\Platform;

/**
 * Resolved runtime configuration. Stateless: no databaseUrl, no httpAddr.
 * Defaults from the constructor, overlaid by APP_* env.
 */
final class Config
{
    public function __construct(
        public readonly string $service = 'logger',
        public readonly string $env = 'dev',
        public readonly string $amqpUrl = 'amqp://guest:guest@localhost:5672/',
        public readonly string $otlpEndpoint = 'http://tempo:4318',
        public readonly string $logLevel = 'info',
    ) {
    }

    public static function load(?string $configYamlPath = null): self
    {
        $d = new self();
        $values = [
            'service' => $d->service,
            'env' => $d->env,
            'amqp_url' => $d->amqpUrl,
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
            amqpUrl: $values['amqp_url'],
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

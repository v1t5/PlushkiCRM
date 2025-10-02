<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Plushki\Crm\Platform\Config;

/**
 * HealthController exposes /healthz (always ok), /readyz (postgres + amqp
 * checks, 200/503), and /metrics (Prometheus text exposition).
 */
final class HealthController
{
    public function __construct(
        private readonly Connection $db,
        private readonly Config $config,
    ) {
    }

    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'postgres' => $this->check(fn () => $this->db->executeQuery('SELECT 1')),
            'amqp' => $this->check(fn () => $this->pingAmqp()),
        ];
        $ok = !\in_array(false, array_map(static fn ($r) => $r === 'ok', $checks), true);

        return new JsonResponse(
            ['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks],
            $ok ? 200 : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    public function metrics(): Response
    {
        // Minimal Prometheus exposition. PHP-FPM/FrankenPHP request workers do
        // not share a registry across requests without APCu/Redis, so we keep a
        // single process-up gauge here; richer RED metrics belong in a shared
        // store (Redis) if needed.
        $body = "# HELP app_up 1 if the service process is serving.\n"
            . "# TYPE app_up gauge\n"
            . sprintf("app_up{svc=\"%s\",env=\"%s\"} 1\n", $this->config->service, $this->config->env);

        return new Response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    private function check(callable $fn): string
    {
        try {
            $fn();

            return 'ok';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /** Lightweight reachability probe for RabbitMQ — a quick TCP connect. */
    private function pingAmqp(): void
    {
        $u = parse_url($this->config->amqpUrl);
        $host = $u['host'] ?? 'rabbitmq';
        $port = $u['port'] ?? 5672;
        $sock = @fsockopen($host, (int) $port, $errno, $errstr, 1.0);
        if ($sock === false) {
            throw new \RuntimeException("amqp: {$errstr}");
        }
        fclose($sock);
    }
}

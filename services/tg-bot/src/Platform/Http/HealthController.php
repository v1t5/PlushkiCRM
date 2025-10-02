<?php

declare(strict_types=1);

namespace Plushki\TgBot\Platform\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Plushki\TgBot\Platform\Config;

/**
 * Exposes the liveness/readiness/metrics endpoints. tg-bot is stateless and
 * long-polls Telegram, so there are no DB/AMQP deps to ping — /readyz aliases
 * /healthz.
 */
final class HealthController
{
    public function __construct(private readonly Config $config)
    {
    }

    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function metrics(): Response
    {
        $body = "# HELP app_up 1 if the service process is serving.\n"
            . "# TYPE app_up gauge\n"
            . sprintf("app_up{svc=\"%s\",env=\"%s\"} 1\n", $this->config->service, $this->config->env);

        return new Response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}

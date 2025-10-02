<?php

declare(strict_types=1);

namespace Plushki\TgBot;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Plushki\TgBot\Adapters\Catalog\CatalogClient;
use Plushki\TgBot\Adapters\Orders\OrdersClient;
use Plushki\TgBot\Adapters\Telegram\Api;
use Plushki\TgBot\Adapters\Telegram\Poller;
use Plushki\TgBot\App\Handler;
use Plushki\TgBot\Platform\Config;
use Plushki\TgBot\Platform\Console\PollCommand;
use Plushki\TgBot\Platform\Http\HealthController;
use Plushki\TgBot\Platform\Log;
use Plushki\TgBot\Platform\Otel;

/**
 * Kernel is tg-bot's wire-up. tg-bot is stateless: no DB, no AMQP, no
 * migrations. The HTTP container serves only health/metrics; the Telegram
 * long-poll loop runs in a separate `tg-bot-poll` worker container (the
 * `plushki:poll` command).
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function boot(): void
    {
        Otel::init(
            Config::env('APP_SERVICE') ?? 'tg-bot',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'tg-bot')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.tg_api_base', Config::env('APP_TG_API_BASE') ?? 'https://api.telegram.org')
            ->set('app.tg_bot_token', Config::env('APP_TG_BOT_TOKEN') ?? '')
            ->set('app.tg_poll_timeout', (int) (Config::env('APP_TG_POLL_TIMEOUT_S') ?? 30))
            ->set('app.catalog_url', Config::env('APP_CATALOG_URL') ?? 'http://catalog:8080')
            ->set('app.orders_url', Config::env('APP_ORDERS_URL') ?? 'http://orders:8080');

        $container->extension('framework', [
            'secret' => Config::env('APP_SECRET') ?? 'dev-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);

        $services = $container->services();
        $services->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('string $service', '%app.service%');

        // --- App use case ---
        $services->set(Handler::class);

        // --- Telegram adapter (API client + long-poll loop) ---
        $services->set(Api::class)
            ->arg('$apiBase', '%app.tg_api_base%')
            ->arg('$token', '%app.tg_bot_token%');
        $services->set(Poller::class)
            ->arg('$pollTimeout', '%app.tg_poll_timeout%');

        // --- Outbound HTTP clients (distinct base URLs, wired explicitly) ---
        $services->set(CatalogClient::class)->arg('$baseUrl', '%app.catalog_url%');
        $services->set(OrdersClient::class)->arg('$baseUrl', '%app.orders_url%');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\TgBot\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Infrastructure services (explicit factories) ---
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        // Symfony HTTP client for the Telegram API + catalog/orders adapters.
        $services->set(HttpClientInterface::class)
            ->factory([HttpClient::class, 'create']);

        // --- Console worker ---
        $services->set(PollCommand::class)
            ->arg('$token', '%app.tg_bot_token%');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);
    }
}

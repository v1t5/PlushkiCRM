<?php

declare(strict_types=1);

namespace Plushki\Notifications;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Plushki\Notifications\Adapters\Db\DeliveryRepo as DbDeliveryRepo;
use Plushki\Notifications\Adapters\Telegram\Sender as TelegramSender;
use Plushki\Notifications\Platform\Amqp;
use Plushki\Notifications\Platform\Config;
use Plushki\Notifications\Platform\Console\InventoryConsumeCommand;
use Plushki\Notifications\Platform\Console\MigrateCommand;
use Plushki\Notifications\Platform\Console\OrdersConsumeCommand;
use Plushki\Notifications\Platform\Db;
use Plushki\Notifications\Platform\Events\Consumer;
use Plushki\Notifications\Platform\Http\HealthController;
use Plushki\Notifications\Platform\Log;
use Plushki\Notifications\Platform\Otel;
use Plushki\Notifications\Ports\DeliveryRepo;
use Plushki\Notifications\Ports\Sender;

/**
 * notifications' wire-up. Boots OpenTelemetry, defines the infrastructure
 * services explicitly, tags the channel senders, aliases the dedup repo, and
 * registers the two consumer workers. There are no HTTP controllers beyond
 * health/metrics — this is a sink.
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
            Config::env('APP_SERVICE') ?? 'notifications',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'notifications')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.tg_api_base', Config::env('APP_TG_API_BASE') ?? 'https://api.telegram.org')
            ->set('app.tg_bot_token', Config::env('APP_TG_BOT_TOKEN') ?? '')
            ->set('app.admin_chat_id', Config::env('APP_ADMIN_CHAT_ID') ?? '');

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
            ->bind('string $service', '%app.service%')
            ->bind('string $apiBase', '%app.tg_api_base%')
            ->bind('string $token', '%app.tg_bot_token%')
            ->bind('string $adminChatId', '%app.admin_chat_id%');

        // Channel senders are collected by the Handler via #[AutowireIterator].
        $services->instanceof(Sender::class)->tag('app.sender');

        // --- App usecases (event value objects excluded) ---
        $services->load('Plushki\\Notifications\\App\\', '../src/App/')
            ->exclude([
                '../src/App/OrderEvent.php',
                '../src/App/OrderEventItem.php',
                '../src/App/StockLowEvent.php',
            ]);

        // --- DB adapters ---
        $services->load('Plushki\\Notifications\\Adapters\\Db\\', '../src/Adapters/Db/');

        // --- Telegram sender ---
        $services->load('Plushki\\Notifications\\Adapters\\Telegram\\', '../src/Adapters/Telegram/');

        // --- Event consumer adapters (envelope -> app, outcome -> ack/nak/term) ---
        $services->load('Plushki\\Notifications\\Adapters\\Events\\', '../src/Adapters/Events/');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Notifications\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(DeliveryRepo::class, DbDeliveryRepo::class);
        $services->alias(Sender::class, TelegramSender::class);

        // --- Infrastructure services (explicit factories) ---
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        // Symfony HTTP client for the Telegram sender; instrumented for OTel.
        $services->set(HttpClientInterface::class)
            ->factory([HttpClient::class, 'create']);

        // AMQP connection is lazy: only the consumer workers pull it.
        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        // Generic AMQP consumer (durable queue -> handler). One per worker process.
        $services->set(Consumer::class);

        // --- Console workers ---
        $services->set(MigrateCommand::class);
        $services->set(OrdersConsumeCommand::class);
        $services->set(InventoryConsumeCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);
    }
}

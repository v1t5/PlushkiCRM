<?php

declare(strict_types=1);

namespace Plushki\Orders;

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
use Plushki\Orders\Adapters\Catalog\HttpCatalogClient;
use Plushki\Orders\Adapters\Db\OrderRepo as DbOrderRepo;
use Plushki\Orders\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Orders\Platform\Amqp;
use Plushki\Orders\Platform\Config;
use Plushki\Orders\Platform\Console\MigrateCommand;
use Plushki\Orders\Platform\Console\OutboxRelayCommand;
use Plushki\Orders\Platform\Db;
use Plushki\Orders\Platform\Events\OutboxStore;
use Plushki\Orders\Platform\Http\HealthController;
use Plushki\Orders\Platform\Log;
use Plushki\Orders\Platform\Otel;
use Plushki\Orders\Ports\CatalogClient;
use Plushki\Orders\Ports\OrderRepo;
use Plushki\Orders\Ports\OutboxRepo;

/**
 * Kernel is orders' wire-up: it boots OpenTelemetry, defines the infrastructure
 * services explicitly, aliases the ports to their adapters, and lets Symfony
 * autowire the thin layers.
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
            Config::env('APP_SERVICE') ?? 'orders',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'orders')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'ORDERS');

        $container->extension('framework', [
            'secret' => Config::env('APP_SECRET') ?? 'dev-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'validation' => ['enable_attributes' => true],
        ]);

        $services = $container->services();
        $services->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('string $service', '%app.service%')
            ->bind('string $outboxExchange', '%app.outbox_exchange%')
            ->bind('string $baseUrl', '%env(APP_CATALOG_URL)%');

        // App usecases; the PlaceItem value object is excluded.
        $services->load('Plushki\\Orders\\App\\', '../src/App/')
            ->exclude(['../src/App/PlaceItem.php']);

        // DB adapter repos; static helpers excluded.
        $services->load('Plushki\\Orders\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude([
                '../src/Adapters/Db/Ts.php',
                '../src/Adapters/Db/PgArray.php',
            ]);

        $services->load('Plushki\\Orders\\Adapters\\Catalog\\', '../src/Adapters/Catalog/');

        // HTTP controllers + subscribers need the controller arg-locator tag.
        $services->load('Plushki\\Orders\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude([
                '../src/Adapters/Http/Dto',
                '../src/Adapters/Http/Api.php',
                '../src/Adapters/Http/Resp.php',
            ])
            ->tag('controller.service_arguments');

        $services->load('Plushki\\Orders\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // Port -> adapter aliases.
        $services->alias(OrderRepo::class, DbOrderRepo::class);
        $services->alias(OutboxRepo::class, DbOutboxRepo::class);
        $services->alias(OutboxStore::class, DbOutboxRepo::class);
        $services->alias(CatalogClient::class, HttpCatalogClient::class);

        // Infrastructure services wired with explicit factories.
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        // Symfony HTTP client for the catalog adapter; instrumented for OTel so
        // a placed order shows catalog as a child span.
        $services->set(HttpClientInterface::class)
            ->factory([HttpClient::class, 'create']);

        // AMQP connection is lazy: only the relay worker pulls it, so HTTP
        // requests never open a broker connection.
        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        // --- Console workers ---
        $services->set(MigrateCommand::class);
        $services->set(OutboxRelayCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Orders' /v1/orders routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

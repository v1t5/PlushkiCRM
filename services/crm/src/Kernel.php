<?php

declare(strict_types=1);

namespace Plushki\Crm;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Crm\Adapters\Db\CustomerRepo as DbCustomerRepo;
use Plushki\Crm\Adapters\Db\LoyaltyRepo as DbLoyaltyRepo;
use Plushki\Crm\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Crm\Platform\Amqp;
use Plushki\Crm\Platform\Config;
use Plushki\Crm\Platform\Console\BootstrapWalkinCommand;
use Plushki\Crm\Platform\Console\MigrateCommand;
use Plushki\Crm\Platform\Console\OrdersConsumeCommand;
use Plushki\Crm\Platform\Console\OutboxRelayCommand;
use Plushki\Crm\Platform\Db;
use Plushki\Crm\Platform\Events\Consumer;
use Plushki\Crm\Platform\Events\OutboxStore;
use Plushki\Crm\Platform\Http\HealthController;
use Plushki\Crm\Platform\Log;
use Plushki\Crm\Platform\Otel;
use Plushki\Crm\Ports\CustomerRepo;
use Plushki\Crm\Ports\LoyaltyRepo;
use Plushki\Crm\Ports\OutboxRepo;

/**
 * Kernel is crm's wire-up. crm is a publisher (outbox → CRM exchange) AND a
 * consumer (orders.v1.fulfilled → loyalty). It boots OpenTelemetry, defines
 * infrastructure services, aliases the ports, and registers the relay +
 * consumer + walk-in-bootstrap console workers.
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
            Config::env('APP_SERVICE') ?? 'crm',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'crm')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'CRM');

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
            ->bind('string $outboxExchange', '%app.outbox_exchange%');

        // --- App usecases (value objects excluded) ---
        $services->load('Plushki\\Crm\\App\\', '../src/App/')
            ->exclude([
                '../src/App/RegisterIdentity.php',
                '../src/App/FulfilledInput.php',
            ]);

        // --- DB adapters (static helpers excluded) ---
        $services->load('Plushki\\Crm\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude([
                '../src/Adapters/Db/Ts.php',
                '../src/Adapters/Db/PgArray.php',
            ]);

        // --- HTTP controllers + subscribers ---
        $services->load('Plushki\\Crm\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude([
                '../src/Adapters/Http/Dto',
                '../src/Adapters/Http/Api.php',
                '../src/Adapters/Http/Resp.php',
            ])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Crm\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(CustomerRepo::class, DbCustomerRepo::class);
        $services->alias(LoyaltyRepo::class, DbLoyaltyRepo::class);
        $services->alias(OutboxRepo::class, DbOutboxRepo::class);
        $services->alias(OutboxStore::class, DbOutboxRepo::class);

        // --- Infrastructure services ---
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        // Generic AMQP consumer (durable queue -> handler). One per worker process.
        $services->set(Consumer::class);

        // --- Console workers ---
        $services->set(MigrateCommand::class);
        $services->set(OutboxRelayCommand::class);
        $services->set(BootstrapWalkinCommand::class);
        $services->set(OrdersConsumeCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // CRM's /v1/customers routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

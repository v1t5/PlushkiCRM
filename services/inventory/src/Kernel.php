<?php

declare(strict_types=1);

namespace Plushki\Inventory;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Inventory\Adapters\Db\IngredientProjectionRepo as DbIngredientProjectionRepo;
use Plushki\Inventory\Adapters\Db\MovementRepo as DbMovementRepo;
use Plushki\Inventory\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Inventory\Adapters\Db\StockRepo as DbStockRepo;
use Plushki\Inventory\Adapters\Db\WarehouseRepo as DbWarehouseRepo;
use Plushki\Inventory\Platform\Amqp;
use Plushki\Inventory\Platform\Config;
use Plushki\Inventory\Platform\Console\BootstrapWarehouseCommand;
use Plushki\Inventory\Platform\Console\CatalogConsumeCommand;
use Plushki\Inventory\Platform\Console\MigrateCommand;
use Plushki\Inventory\Platform\Console\OrdersConsumeCommand;
use Plushki\Inventory\Platform\Console\OutboxRelayCommand;
use Plushki\Inventory\Platform\Console\ProductionConsumeCommand;
use Plushki\Inventory\Platform\Db;
use Plushki\Inventory\Platform\Events\Consumer;
use Plushki\Inventory\Platform\Events\OutboxStore;
use Plushki\Inventory\Platform\Http\HealthController;
use Plushki\Inventory\Platform\Log;
use Plushki\Inventory\Platform\Otel;
use Plushki\Inventory\Ports\IngredientProjectionRepo;
use Plushki\Inventory\Ports\MovementRepo;
use Plushki\Inventory\Ports\OutboxRepo;
use Plushki\Inventory\Ports\StockRepo;
use Plushki\Inventory\Ports\WarehouseRepo;

/**
 * Kernel is inventory's wire-up. inventory is a publisher (outbox → INVENTORY
 * exchange) AND a consumer (catalog/orders/production). It boots OpenTelemetry,
 * defines infrastructure services, aliases the ports to their DBAL adapters, and
 * registers the relay + consumer + bootstrap console workers.
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
            Config::env('APP_SERVICE') ?? 'inventory',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'inventory')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'INVENTORY')
            ->set('app.default_warehouse_code', Config::env('APP_DEFAULT_WAREHOUSE_CODE') ?? 'main')
            ->set('app.default_warehouse_name', Config::env('APP_DEFAULT_WAREHOUSE_NAME') ?? 'Main warehouse');

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
            ->bind('string $defaultWarehouseCode', '%app.default_warehouse_code%')
            ->bind('string $defaultWarehouseName', '%app.default_warehouse_name%');

        // --- App usecases (value objects excluded) ---
        $services->load('Plushki\\Inventory\\App\\', '../src/App/')
            ->exclude([
                '../src/App/PostMovementInput.php',
                '../src/App/EventLine.php',
            ]);

        // --- DB adapters (repos; static helpers excluded) ---
        $services->load('Plushki\\Inventory\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude([
                '../src/Adapters/Db/Ts.php',
                '../src/Adapters/Db/PgArray.php',
            ]);

        // --- HTTP controllers + subscribers (need the controller arg-locator tag) ---
        $services->load('Plushki\\Inventory\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude([
                '../src/Adapters/Http/Dto',
                '../src/Adapters/Http/Api.php',
                '../src/Adapters/Http/Resp.php',
            ])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Inventory\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(WarehouseRepo::class, DbWarehouseRepo::class);
        $services->alias(StockRepo::class, DbStockRepo::class);
        $services->alias(MovementRepo::class, DbMovementRepo::class);
        $services->alias(IngredientProjectionRepo::class, DbIngredientProjectionRepo::class);
        $services->alias(OutboxRepo::class, DbOutboxRepo::class);
        $services->alias(OutboxStore::class, DbOutboxRepo::class);

        // --- Infrastructure services (explicit factories) ---
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        // AMQP connection is lazy: only the relay + consumer workers pull it.
        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        // Generic AMQP consumer (durable queue -> handler). One per worker process.
        $services->set(Consumer::class);

        // --- Console workers ---
        $services->set(MigrateCommand::class);
        $services->set(OutboxRelayCommand::class);
        $services->set(BootstrapWarehouseCommand::class);
        $services->set(CatalogConsumeCommand::class);
        $services->set(OrdersConsumeCommand::class);
        $services->set(ProductionConsumeCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Inventory's /v1/warehouses, /v1/movements, /v1/stock routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

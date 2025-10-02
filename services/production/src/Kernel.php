<?php

declare(strict_types=1);

namespace Plushki\Production;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Production\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Production\Adapters\Db\PlanRepo as DbPlanRepo;
use Plushki\Production\Adapters\Db\RecipeProjectionRepo as DbRecipeProjectionRepo;
use Plushki\Production\Adapters\Db\TaskRepo as DbTaskRepo;
use Plushki\Production\Platform\Amqp;
use Plushki\Production\Platform\Config;
use Plushki\Production\Platform\Console\CatalogConsumeCommand;
use Plushki\Production\Platform\Console\MigrateCommand;
use Plushki\Production\Platform\Console\OrdersConsumeCommand;
use Plushki\Production\Platform\Console\OutboxRelayCommand;
use Plushki\Production\Platform\Db;
use Plushki\Production\Platform\Events\Consumer;
use Plushki\Production\Platform\Events\OutboxStore;
use Plushki\Production\Platform\Http\HealthController;
use Plushki\Production\Platform\Log;
use Plushki\Production\Platform\Otel;
use Plushki\Production\Ports\OutboxRepo;
use Plushki\Production\Ports\PlanRepo;
use Plushki\Production\Ports\RecipeProjectionRepo;
use Plushki\Production\Ports\TaskRepo;

/**
 * Production's wire-up. Production is a publisher (outbox → PRODUCTION exchange)
 * AND a consumer (catalog recipe_updated, orders confirmed). It boots
 * OpenTelemetry, defines infrastructure services, aliases the ports, and
 * registers the relay + consumer console workers.
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
            Config::env('APP_SERVICE') ?? 'production',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'production')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'PRODUCTION');

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

        // --- App usecases ---
        $services->load('Plushki\\Production\\App\\', '../src/App/');

        // --- DB adapters (static helpers excluded) ---
        $services->load('Plushki\\Production\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude([
                '../src/Adapters/Db/Ts.php',
                '../src/Adapters/Db/PgArray.php',
            ]);

        // --- HTTP controllers + subscribers ---
        $services->load('Plushki\\Production\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude([
                '../src/Adapters/Http/Dto',
                '../src/Adapters/Http/Api.php',
                '../src/Adapters/Http/Resp.php',
            ])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Production\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(PlanRepo::class, DbPlanRepo::class);
        $services->alias(TaskRepo::class, DbTaskRepo::class);
        $services->alias(RecipeProjectionRepo::class, DbRecipeProjectionRepo::class);
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
        $services->set(CatalogConsumeCommand::class);
        $services->set(OrdersConsumeCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Production's /v1/plans, /v1/tasks routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

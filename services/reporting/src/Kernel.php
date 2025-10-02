<?php

declare(strict_types=1);

namespace Plushki\Reporting;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Reporting\Adapters\Db\ProjectionRepo as DbProjectionRepo;
use Plushki\Reporting\Platform\Amqp;
use Plushki\Reporting\Platform\Config;
use Plushki\Reporting\Platform\Console\MigrateCommand;
use Plushki\Reporting\Platform\Console\MovementsConsumeCommand;
use Plushki\Reporting\Platform\Console\OrdersConsumeCommand;
use Plushki\Reporting\Platform\Console\StockLowConsumeCommand;
use Plushki\Reporting\Platform\Db;
use Plushki\Reporting\Platform\Events\Consumer;
use Plushki\Reporting\Platform\Http\HealthController;
use Plushki\Reporting\Platform\Log;
use Plushki\Reporting\Platform\Otel;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * Reporting's service wire-up. Reporting is a read-only projector: it publishes
 * nothing (no outbox/relay), consumes three event streams, and exposes
 * read-only query endpoints.
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
            Config::env('APP_SERVICE') ?? 'reporting',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'reporting')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info');

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

        // --- DB adapters (static helpers excluded) ---
        $services->load('Plushki\\Reporting\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude(['../src/Adapters/Db/Ts.php']);

        // --- HTTP read controllers + subscribers ---
        $services->load('Plushki\\Reporting\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude(['../src/Adapters/Http/Api.php'])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Reporting\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter alias ---
        $services->alias(ProjectionRepo::class, DbProjectionRepo::class);

        // --- Infrastructure services ---
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

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
        $services->set(StockLowConsumeCommand::class);
        $services->set(MovementsConsumeCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Reporting's /v1/sales, /v1/inventory read routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

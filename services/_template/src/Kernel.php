<?php

declare(strict_types=1);

namespace Plushki\Template;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Template\Platform\Amqp;
use Plushki\Template\Platform\Config;
use Plushki\Template\Platform\Db;
use Plushki\Template\Platform\Http\HealthController;
use Plushki\Template\Platform\Log;
use Plushki\Template\Platform\Otel;

/**
 * Service wire-up: boots OpenTelemetry, defines the infrastructure services
 * (Config, DBAL Connection, AMQP connection, Logger) explicitly, and lets
 * Symfony autowire the thin layers on top.
 *
 * Each service owns its own copy of this file and extends configureContainer /
 * configureRoutes with its domain services and controllers.
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
        // Initialise tracing before anything handles a request.
        Otel::init(
            Config::env('APP_SERVICE') ?? 'template',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $service = Config::env('APP_SERVICE') ?? 'template';

        $container->parameters()->set('app.service', $service);
        $container->parameters()->set('app.env', Config::env('APP_ENV') ?? 'dev');
        $container->parameters()->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info');

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

        // App layers (autowired). Non-service classes are excluded.
        $services->load('Plushki\\Template\\', '../src/')
            ->exclude([
                '../src/Kernel.php',
                '../src/Platform/Config.php',
                '../src/Platform/Db.php',
                '../src/Platform/Migrator.php',
                '../src/Platform/Amqp.php',
                '../src/Platform/Log.php',
                '../src/Platform/Otel.php',
                '../src/Platform/Problem.php',
                '../src/Platform/ProblemException.php',
                '../src/Platform/Events',
                '../src/Platform/Console',
                '../src/Domain',
            ]);

        // Console commands are registered explicitly so each container only
        // wires the ones whose dependencies exist. MigrateCommand needs only
        // the DB; the outbox-relay command (added by publishing services) also
        // needs an OutboxStore binding.
        $services->set(Platform\Console\MigrateCommand::class);

        // Infrastructure services (explicit factories).
        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(Connection::class)
            ->factory([Db::class, 'open'])
            ->args(['%env(APP_DATABASE_URL)%']);

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        // AMQP connection is lazy: only worker commands (relay/consumer) pull it,
        // so HTTP requests never open a broker connection.
        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Services add their HTTP routes here, e.g.:
        //   $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

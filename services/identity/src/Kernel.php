<?php

declare(strict_types=1);

namespace Plushki\Identity;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Identity\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Identity\Adapters\Db\RefreshTokenRepo as DbRefreshTokenRepo;
use Plushki\Identity\Adapters\Db\ServiceTokenRepo as DbServiceTokenRepo;
use Plushki\Identity\Adapters\Db\UserRepo as DbUserRepo;
use Plushki\Identity\App\JwtIssuer;
use Plushki\Identity\App\JwtIssuerFactory;
use Plushki\Identity\Platform\Amqp;
use Plushki\Identity\Platform\Config;
use Plushki\Identity\Platform\Console\MigrateCommand;
use Plushki\Identity\Platform\Console\OutboxRelayCommand;
use Plushki\Identity\Platform\Db;
use Plushki\Identity\Platform\Events\OutboxStore;
use Plushki\Identity\Platform\Http\HealthController;
use Plushki\Identity\Platform\Log;
use Plushki\Identity\Platform\Otel;
use Plushki\Identity\Ports\OutboxRepo;
use Plushki\Identity\Ports\RefreshTokenRepo;
use Plushki\Identity\Ports\ServiceTokenRepo;
use Plushki\Identity\Ports\UserRepo;

/**
 * Kernel is identity's wire-up. It boots OpenTelemetry, defines the
 * infrastructure services explicitly, aliases the ports to their DBAL adapters,
 * and lets Symfony autowire the thin layers.
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
            Config::env('APP_SERVICE') ?? 'identity',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'identity')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'IDENTITY');

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
            ->bind('string $projectDir', '%kernel.project_dir%')
            ->bind('string $outboxExchange', '%app.outbox_exchange%');

        // --- App usecases (value objects + the factory-built issuer excluded) ---
        $services->load('Plushki\\Identity\\App\\', '../src/App/')
            ->exclude([
                '../src/App/TokenPair.php',
                '../src/App/IntrospectResult.php',
                '../src/App/JwtIssuer.php',
            ]);

        // --- DB adapters (repos + static helpers) ---
        $services->load('Plushki\\Identity\\Adapters\\Db\\', '../src/Adapters/Db/');

        // --- Console adapters (bootstrap-admin) ---
        $services->load('Plushki\\Identity\\Adapters\\Console\\', '../src/Adapters/Console/');

        // --- HTTP controllers + subscribers (need the controller arg-locator tag) ---
        $services->load('Plushki\\Identity\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude(['../src/Adapters/Http/Dto'])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Identity\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(UserRepo::class, DbUserRepo::class);
        $services->alias(RefreshTokenRepo::class, DbRefreshTokenRepo::class);
        $services->alias(ServiceTokenRepo::class, DbServiceTokenRepo::class);
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

        // AMQP connection is lazy: only worker commands (relay) pull it, so HTTP
        // requests never open a broker connection.
        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        // --- JWT issuer ---
        $services->set(JwtIssuer::class)->factory([service(JwtIssuerFactory::class), 'create']);

        // --- Console workers ---
        $services->set(MigrateCommand::class);
        $services->set(OutboxRelayCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('healthz', '/healthz')->controller([HealthController::class, 'live']);
        $routes->add('readyz', '/readyz')->controller([HealthController::class, 'ready']);
        $routes->add('metrics', '/metrics')->controller([HealthController::class, 'metrics']);

        // Identity's /auth/*, /me, /admin/*, /.well-known/jwks.json routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Logger;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Logger\Adapters\Events\Tap;
use Plushki\Logger\Platform\Amqp;
use Plushki\Logger\Platform\Config;
use Plushki\Logger\Platform\Console\TapCommand;
use Plushki\Logger\Platform\Log;
use Plushki\Logger\Platform\Otel;

/**
 * Wire-up for the logger sentinel. Stateless: no DB, no HTTP, no migrations —
 * a single `logger` worker container running the `plushki:tap` command.
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
            Config::env('APP_SERVICE') ?? 'logger',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'logger')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info');

        $container->extension('framework', [
            'secret' => Config::env('APP_SECRET') ?? 'dev-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $services = $container->services();
        $services->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('string $service', '%app.service%');

        $services->set(Config::class)->factory([Config::class, 'load'])->public();

        $services->set(LoggerInterface::class)
            ->factory([Log::class, 'new'])
            ->args(['%app.service%', '%app.env%', '%app.log_level%']);

        $services->set(AMQPStreamConnection::class)
            ->factory([Amqp::class, 'connect'])
            ->args(['%env(APP_AMQP_URL)%', '%app.service%'])
            ->lazy();

        $services->set(Tap::class);
        $services->set(TapCommand::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        // No HTTP — this is a pure AMQP tap worker.
    }
}

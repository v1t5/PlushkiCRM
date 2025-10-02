<?php

declare(strict_types=1);

namespace Plushki\Catalog;

use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Plushki\Catalog\Adapters\Db\CategoryRepo as DbCategoryRepo;
use Plushki\Catalog\Adapters\Db\IngredientRepo as DbIngredientRepo;
use Plushki\Catalog\Adapters\Db\OutboxRepo as DbOutboxRepo;
use Plushki\Catalog\Adapters\Db\ProductRepo as DbProductRepo;
use Plushki\Catalog\Adapters\Db\RecipeRepo as DbRecipeRepo;
use Plushki\Catalog\Adapters\Db\UnitRepo as DbUnitRepo;
use Plushki\Catalog\Platform\Amqp;
use Plushki\Catalog\Platform\Config;
use Plushki\Catalog\Platform\Console\MigrateCommand;
use Plushki\Catalog\Platform\Console\OutboxRelayCommand;
use Plushki\Catalog\Platform\Db;
use Plushki\Catalog\Platform\Events\OutboxStore;
use Plushki\Catalog\Platform\Http\HealthController;
use Plushki\Catalog\Platform\Log;
use Plushki\Catalog\Platform\Otel;
use Plushki\Catalog\Ports\CategoryRepo;
use Plushki\Catalog\Ports\IngredientRepo;
use Plushki\Catalog\Ports\OutboxRepo;
use Plushki\Catalog\Ports\ProductRepo;
use Plushki\Catalog\Ports\RecipeRepo;
use Plushki\Catalog\Ports\UnitRepo;

/**
 * Catalog's wire-up: boots OpenTelemetry, defines the infrastructure services
 * explicitly, aliases the ports to their DBAL adapters, and lets Symfony
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
            Config::env('APP_SERVICE') ?? 'catalog',
            Config::env('APP_OTLP_ENDPOINT') ?? 'http://tempo:4318',
        );
        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.service', Config::env('APP_SERVICE') ?? 'catalog')
            ->set('app.env', Config::env('APP_ENV') ?? 'dev')
            ->set('app.log_level', Config::env('APP_LOG_LEVEL') ?? 'info')
            ->set('app.outbox_exchange', Config::env('APP_OUTBOX_EXCHANGE') ?? 'CATALOG');

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

        // --- App usecases (the RecipeLineInput value object excluded) ---
        $services->load('Plushki\\Catalog\\App\\', '../src/App/')
            ->exclude(['../src/App/RecipeLineInput.php']);

        // --- DB adapters (repos; static helpers excluded) ---
        $services->load('Plushki\\Catalog\\Adapters\\Db\\', '../src/Adapters/Db/')
            ->exclude([
                '../src/Adapters/Db/Ts.php',
                '../src/Adapters/Db/PgArray.php',
            ]);

        // --- HTTP controllers + subscribers (need the controller arg-locator tag) ---
        $services->load('Plushki\\Catalog\\Adapters\\Http\\', '../src/Adapters/Http/')
            ->exclude([
                '../src/Adapters/Http/Dto',
                '../src/Adapters/Http/Api.php',
                '../src/Adapters/Http/Resp.php',
            ])
            ->tag('controller.service_arguments');

        // --- Platform HTTP (health controller + generic subscribers) ---
        $services->load('Plushki\\Catalog\\Platform\\Http\\', '../src/Platform/Http/')
            ->tag('controller.service_arguments');

        // --- Port -> adapter aliases ---
        $services->alias(CategoryRepo::class, DbCategoryRepo::class);
        $services->alias(ProductRepo::class, DbProductRepo::class);
        $services->alias(UnitRepo::class, DbUnitRepo::class);
        $services->alias(IngredientRepo::class, DbIngredientRepo::class);
        $services->alias(RecipeRepo::class, DbRecipeRepo::class);
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

        // Catalog's /v1/categories, /v1/products, /v1/units, /v1/ingredients routes.
        $routes->import('../src/Adapters/Http/', 'attribute');
    }
}
<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Inventory\Adapters\Events\ProductionConsumer;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\App\WarehouseService;
use Plushki\Inventory\Platform\Events\Consumer;

/**
 * `plushki:consume-production` runs the PRODUCTION task_completed→CONSUMED
 * consumer worker. The binding is declared on startup even before production
 * publishes anything (exchange/queue declaration is idempotent).
 */
#[AsCommand(name: 'plushki:consume-production', description: 'Post CONSUMED_BY_PRODUCTION movements from production.v1.task_completed')]
final class ProductionConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly MovementService $movements,
        private readonly WarehouseService $warehouses,
        private readonly LoggerInterface $logger,
        private readonly string $defaultWarehouseCode,
        private readonly string $defaultWarehouseName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $warehouse = $this->warehouses->ensureDefault($this->defaultWarehouseCode, $this->defaultWarehouseName);
        $handler = new ProductionConsumer($this->movements, $warehouse->id, $this->logger);
        $this->consumer->run(
            exchange: 'PRODUCTION',
            queue: 'inventory-production',
            bindingKey: 'production.v1.task_completed.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

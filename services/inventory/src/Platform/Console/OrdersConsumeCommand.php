<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Inventory\Adapters\Events\OrdersConsumer;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\App\WarehouseService;
use Plushki\Inventory\Platform\Events\Consumer;

/**
 * `plushki:consume-orders` runs the ORDERS fulfilled→SOLD consumer worker. The
 * warehouse is resolved at startup (ensureDefault) before binding the consumer.
 */
#[AsCommand(name: 'plushki:consume-orders', description: 'Post SOLD movements from orders.v1.fulfilled')]
final class OrdersConsumeCommand extends Command
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
        $handler = new OrdersConsumer($this->movements, $warehouse->id, $this->logger);
        $this->consumer->run(
            exchange: 'ORDERS',
            queue: 'inventory-orders',
            bindingKey: 'orders.v1.fulfilled.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

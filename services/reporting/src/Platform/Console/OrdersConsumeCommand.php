<?php

declare(strict_types=1);

namespace Plushki\Reporting\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Reporting\Adapters\Events\OrdersConsumer;
use Plushki\Reporting\Platform\Events\Consumer;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * `plushki:consume-orders-fulfilled` — ORDERS fulfilled → sales projections.
 */
#[AsCommand(name: 'plushki:consume-orders-fulfilled', description: 'Project orders.v1.fulfilled into sales_by_day + top_items')]
final class OrdersConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly ProjectionRepo $repo,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new OrdersConsumer($this->repo, $this->logger);
        $this->consumer->run(
            exchange: 'ORDERS',
            queue: 'reporting-orders-fulfilled',
            bindingKey: 'orders.v1.fulfilled.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Plushki\Production\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Production\Adapters\Events\OrdersConsumer;
use Plushki\Production\App\PlanService;
use Plushki\Production\Platform\Events\Consumer;

/**
 * `plushki:consume-orders` runs the ORDERS confirmed→plan-accumulation consumer
 * worker.
 */
#[AsCommand(name: 'plushki:consume-orders', description: 'Accumulate orders.v1.confirmed into the daily plan')]
final class OrdersConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly PlanService $plans,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new OrdersConsumer($this->plans, $this->logger);
        $this->consumer->run(
            exchange: 'ORDERS',
            queue: 'production-orders',
            bindingKey: 'orders.v1.confirmed.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

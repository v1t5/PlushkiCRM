<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Crm\Adapters\Events\OrdersConsumer;
use Plushki\Crm\App\LoyaltyService;
use Plushki\Crm\Platform\Events\Consumer;

/**
 * `plushki:consume-orders` runs the ORDERS fulfilled→loyalty consumer worker.
 */
#[AsCommand(name: 'plushki:consume-orders', description: 'Bump loyalty from orders.v1.fulfilled')]
final class OrdersConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly LoyaltyService $loyalty,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new OrdersConsumer($this->loyalty, $this->logger);
        $this->consumer->run(
            exchange: 'ORDERS',
            queue: 'crm-orders',
            bindingKey: 'orders.v1.fulfilled.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

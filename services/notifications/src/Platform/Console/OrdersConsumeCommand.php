<?php

declare(strict_types=1);

namespace Plushki\Notifications\Platform\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Notifications\Adapters\Events\OrdersConsumer;
use Plushki\Notifications\Platform\Events\Consumer;

/**
 * `plushki:consume-orders` runs the long-lived ORDERS consumer worker (one
 * `notifications-consume-orders` container). Binds a durable queue
 * `notifications-orders` to the ORDERS topic exchange with `orders.v1.#` and
 * dispatches each envelope to the OrdersConsumer adapter.
 */
#[AsCommand(name: 'plushki:consume-orders', description: 'Consume orders.v1.# from the ORDERS exchange and send notifications')]
final class OrdersConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly OrdersConsumer $handler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->consumer->run(
            exchange: 'ORDERS',
            queue: 'notifications-orders',
            bindingKey: 'orders.v1.#',
            handler: $this->handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

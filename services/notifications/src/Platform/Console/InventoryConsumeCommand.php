<?php

declare(strict_types=1);

namespace Plushki\Notifications\Platform\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Notifications\Adapters\Events\InventoryConsumer;
use Plushki\Notifications\Platform\Events\Consumer;

/**
 * `plushki:consume-inventory` runs the long-lived INVENTORY consumer worker (one
 * `notifications-consume-inventory` container). Binds a durable queue
 * `notifications-inventory` to the INVENTORY topic exchange with
 * `inventory.v1.stock_low.#` and routes alerts to the admin chat.
 */
#[AsCommand(name: 'plushki:consume-inventory', description: 'Consume inventory.v1.stock_low.# from the INVENTORY exchange')]
final class InventoryConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly InventoryConsumer $handler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->consumer->run(
            exchange: 'INVENTORY',
            queue: 'notifications-inventory',
            bindingKey: 'inventory.v1.stock_low.#',
            handler: $this->handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

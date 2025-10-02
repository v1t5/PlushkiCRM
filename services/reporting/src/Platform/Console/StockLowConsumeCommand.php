<?php

declare(strict_types=1);

namespace Plushki\Reporting\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Reporting\Adapters\Events\StockLowConsumer;
use Plushki\Reporting\Platform\Events\Consumer;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * `plushki:consume-stock-low` — INVENTORY stock_low → stock_low_events log.
 */
#[AsCommand(name: 'plushki:consume-stock-low', description: 'Append inventory.v1.stock_low into stock_low_events')]
final class StockLowConsumeCommand extends Command
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
        $handler = new StockLowConsumer($this->repo, $this->logger);
        $this->consumer->run(
            exchange: 'INVENTORY',
            queue: 'reporting-stock-low',
            bindingKey: 'inventory.v1.stock_low.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

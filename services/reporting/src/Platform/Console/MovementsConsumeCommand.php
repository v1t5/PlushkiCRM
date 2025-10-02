<?php

declare(strict_types=1);

namespace Plushki\Reporting\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Reporting\Adapters\Events\MovementsConsumer;
use Plushki\Reporting\Platform\Events\Consumer;
use Plushki\Reporting\Ports\ProjectionRepo;

/**
 * `plushki:consume-movements` — INVENTORY movement_posted → movements_by_day.
 */
#[AsCommand(name: 'plushki:consume-movements', description: 'Project inventory.v1.movement_posted into movements_by_day')]
final class MovementsConsumeCommand extends Command
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
        $handler = new MovementsConsumer($this->repo, $this->logger);
        $this->consumer->run(
            exchange: 'INVENTORY',
            queue: 'reporting-inventory-movements',
            bindingKey: 'inventory.v1.movement_posted.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

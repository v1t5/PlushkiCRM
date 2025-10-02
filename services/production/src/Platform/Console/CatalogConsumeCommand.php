<?php

declare(strict_types=1);

namespace Plushki\Production\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Production\Adapters\Events\CatalogConsumer;
use Plushki\Production\Platform\Events\Consumer;
use Plushki\Production\Ports\RecipeProjectionRepo;

/**
 * `plushki:consume-catalog` runs the CATALOG recipe-projection consumer worker.
 */
#[AsCommand(name: 'plushki:consume-catalog', description: 'Project catalog.v1.recipe_updated into recipe_projection')]
final class CatalogConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly RecipeProjectionRepo $projection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new CatalogConsumer($this->projection, $this->logger);
        $this->consumer->run(
            exchange: 'CATALOG',
            queue: 'production-catalog',
            bindingKey: 'catalog.v1.recipe_updated.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

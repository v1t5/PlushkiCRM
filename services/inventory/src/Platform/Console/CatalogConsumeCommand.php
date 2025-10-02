<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Inventory\Adapters\Events\CatalogConsumer;
use Plushki\Inventory\Platform\Events\Consumer;
use Plushki\Inventory\Ports\IngredientProjectionRepo;

/**
 * `plushki:consume-catalog` runs the CATALOG ingredient-projection consumer
 * worker.
 */
#[AsCommand(name: 'plushki:consume-catalog', description: 'Project catalog.v1.ingredient_created into ingredient_projection')]
final class CatalogConsumeCommand extends Command
{
    public function __construct(
        private readonly Consumer $consumer,
        private readonly IngredientProjectionRepo $projection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new CatalogConsumer($this->projection, $this->logger);
        $this->consumer->run(
            exchange: 'CATALOG',
            queue: 'inventory-catalog',
            bindingKey: 'catalog.v1.ingredient_created.#',
            handler: $handler->handle(...),
        );

        return Command::SUCCESS;
    }
}

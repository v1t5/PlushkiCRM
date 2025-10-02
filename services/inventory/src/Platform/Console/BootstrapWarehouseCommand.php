<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Inventory\App\WarehouseService;

/**
 * `plushki:bootstrap-warehouse` ensures the default warehouse exists. Runs as
 * part of the migrate one-shot so the HTTP server and consumers have a warehouse
 * to post against. Idempotent.
 */
#[AsCommand(name: 'plushki:bootstrap-warehouse', description: 'Create the default warehouse if it does not exist')]
final class BootstrapWarehouseCommand extends Command
{
    public function __construct(
        private readonly WarehouseService $warehouses,
        private readonly LoggerInterface $logger,
        private readonly string $defaultWarehouseCode,
        private readonly string $defaultWarehouseName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $w = $this->warehouses->ensureDefault($this->defaultWarehouseCode, $this->defaultWarehouseName);
        $this->logger->info('default warehouse ready', ['warehouse_id' => $w->id, 'code' => $w->code]);
        $output->writeln(sprintf('default warehouse ready: %s (%s)', $w->id, $w->code));

        return Command::SUCCESS;
    }
}

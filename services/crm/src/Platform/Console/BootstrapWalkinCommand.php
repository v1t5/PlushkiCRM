<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Crm\App\CustomerService;

/**
 * `plushki:bootstrap-walkin` ensures the default tenant's walk-in customer
 * exists, so the first POS sale has somewhere to land. Idempotent.
 */
#[AsCommand(name: 'plushki:bootstrap-walkin', description: 'Create the default tenant walk-in customer if missing')]
final class BootstrapWalkinCommand extends Command
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $c = $this->customers->ensureWalkin('default');
        $this->logger->info('walk-in customer ready', ['customer_id' => $c->id]);
        $output->writeln('walk-in customer ready: ' . $c->id);

        return Command::SUCCESS;
    }
}

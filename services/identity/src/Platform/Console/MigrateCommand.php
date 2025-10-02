<?php

declare(strict_types=1);

namespace Plushki\Identity\Platform\Console;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Identity\Platform\Migrator;

/**
 * `plushki:migrate` applies pending up-migrations. Each service's container runs
 * this once on boot before serving / consuming.
 */
#[AsCommand(name: 'plushki:migrate', description: 'Apply pending SQL migrations')]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // src/Platform/Console -> service root is three levels up.
        $migrationsDir = \dirname(__DIR__, 3) . '/migrations';
        (new Migrator($this->db, $migrationsDir, $this->logger))->up();
        $output->writeln('<info>migrations applied</info>');

        return Command::SUCCESS;
    }
}

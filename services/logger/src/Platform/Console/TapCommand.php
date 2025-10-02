<?php

declare(strict_types=1);

namespace Plushki\Logger\Platform\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Logger\Adapters\Events\Tap;

/** `plushki:tap` runs the logger sentinel: one `logger` container; no HTTP, no DB. */
#[AsCommand(name: 'plushki:tap', description: 'Tap every topic exchange (#) and log each event seen')]
final class TapCommand extends Command
{
    public function __construct(private readonly Tap $tap)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tap->run();

        return Command::SUCCESS;
    }
}

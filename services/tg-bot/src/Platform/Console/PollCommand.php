<?php

declare(strict_types=1);

namespace Plushki\TgBot\Platform\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\TgBot\Adapters\Telegram\Poller;
use Plushki\TgBot\App\Handler;

/**
 * `plushki:poll` runs the long-lived Telegram long-poll worker (one
 * `tg-bot-poll` container). When APP_TG_BOT_TOKEN is empty the poller is
 * disabled and the command exits cleanly, so dev stacks without a token don't
 * loop on a bad getUpdates URL.
 */
#[AsCommand(name: 'plushki:poll', description: 'Long-poll Telegram getUpdates and drive the bot handler')]
final class PollCommand extends Command
{
    public function __construct(
        private readonly Poller $poller,
        private readonly Handler $handler,
        private readonly string $token,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->token === '') {
            $output->writeln('<comment>APP_TG_BOT_TOKEN is empty — poller disabled.</comment>');

            return Command::SUCCESS;
        }

        $this->poller->run($this->handler->handleUpdate(...));

        return Command::SUCCESS;
    }
}

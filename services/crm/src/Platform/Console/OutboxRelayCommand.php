<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Console;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Crm\Platform\Events\OutboxRelay;
use Plushki\Crm\Platform\Events\OutboxStore;

/**
 * `plushki:outbox-relay` runs the long-lived outbox relay worker. One
 * `<svc>-relay` container per publishing service.
 *
 * The relay is generic; the exchange name comes from APP_OUTBOX_EXCHANGE
 * (IDENTITY / CATALOG / ORDERS / ...). Each publishing service binds the
 * OutboxStore interface to its own DB outbox repository.
 */
#[AsCommand(name: 'plushki:outbox-relay', description: 'Drain the outbox into the service topic exchange')]
final class OutboxRelayCommand extends Command
{
    public function __construct(
        private readonly OutboxStore $store,
        private readonly AMQPStreamConnection $conn,
        private readonly LoggerInterface $logger,
        private readonly string $outboxExchange,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->outboxExchange === '') {
            $output->writeln('<error>APP_OUTBOX_EXCHANGE is required for the relay</error>');

            return Command::FAILURE;
        }

        (new OutboxRelay($this->store, $this->conn, $this->outboxExchange, $this->logger))
            ->run(intervalMs: 500, batchSize: 100);

        return Command::SUCCESS;
    }
}

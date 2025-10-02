<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Config;
use Plushki\Identity\Ports\UserRepo;

/**
 * `plushki:bootstrap-admin` grants the "admin" role to APP_BOOTSTRAP_ADMIN_EMAIL
 * if that user exists and is missing it. A missing user is a no-op (register
 * first, then this runs on the next start). Runs once on startup after
 * migrations.
 */
#[AsCommand(name: 'plushki:bootstrap-admin', description: 'Grant admin to APP_BOOTSTRAP_ADMIN_EMAIL if present')]
final class BootstrapAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepo $users,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim($this->config->bootstrapAdminEmail);
        if ($email === '') {
            return Command::SUCCESS;
        }

        try {
            $u = $this->users->getByEmail('default', $email);
        } catch (DomainException $e) {
            if ($e->errorCode === ErrorCode::UserNotFound) {
                $this->logger->info('bootstrap admin: user not found, skipping', ['email' => $email]);

                return Command::SUCCESS;
            }
            throw $e;
        }

        if ($u->hasRole(User::ADMIN_ROLE)) {
            $this->logger->info('bootstrap admin: already has role', ['user_id' => $u->id, 'email' => $email]);

            return Command::SUCCESS;
        }

        $this->users->updateRoles($u->id, [...$u->roles, User::ADMIN_ROLE]);
        $this->logger->info('bootstrap admin: granted', ['user_id' => $u->id, 'email' => $email]);

        return Command::SUCCESS;
    }
}

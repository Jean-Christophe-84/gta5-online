<?php

namespace App\Command;

use App\Helper\UserHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsCommand(
    name       : 'users:purge',
    description: 'Warn then anonymize inactive accounts, and anonymize accounts whose mail was never verified'
)]
class PurgeUsersCommand extends Command
{
    public function __construct(private readonly UserHelper $userHelper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without doing it');
    }

    /**
     * @throws DoctrineDBALException|Exception|TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $toAnonymize = array_merge($this->userHelper->getUnverifiedUsers(), $this->userHelper->getInactiveUsersToAnonymize());
        $toWarn      = $this->userHelper->getInactiveUsersToWarn();

        $output->writeln(count($toAnonymize) . ' account(s) to anonymize, ' . count($toWarn) . ' account(s) to warn');

        if ($input->getOption('dry-run')) {
            return Command::SUCCESS;
        }

        foreach ($toAnonymize as $user) {
            $this->userHelper->anonymize($user);
        }

        foreach ($toWarn as $user) {
            $this->userHelper->sendInactivityWarning($user);
        }

        return Command::SUCCESS;
    }
}

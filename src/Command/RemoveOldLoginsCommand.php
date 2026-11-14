<?php

declare(strict_types=1);

namespace App\Command;

use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'RemoveOldLoginsCommand',
    description: 'Remove failed and successful logins older than the retention period',
    aliases    : ['logins:remove-old']
)]
class RemoveOldLoginsCommand extends Command
{
    const string RETENTION = '1 year';
    const array TABLES     = ['login_error', 'login_success'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct(AsCommand::class);
    }

    /**
     * @throws DateMalformedStringException|DoctrineDBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = new DateTime('- ' . self::RETENTION)->format('Y-m-d H:i:s');

        foreach (self::TABLES as $table) {
            $count = $this->entityManager->getConnection()->executeStatement('DELETE FROM ' . $table . ' WHERE date < :limit', ['limit' => $limit]);

            $output->writeln([$count . ' row(s) removed from ' . $table]);
        }

        return Command::SUCCESS;
    }
}

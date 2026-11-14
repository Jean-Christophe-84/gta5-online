<?php

namespace App\Command;

use App\Helper\BackupHelper;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'app:backup',
    description: 'Sauvegarde la base dans le dossier backup (dernier état + historique élagué)'
)]
class BackupCommand extends Command
{
    public function __construct(private readonly BackupHelper $backupHelper)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->backupHelper->backup();

        $output->writeln($file === null ? 'La base n\'a pas changé depuis la dernière sauvegarde' : 'Sauvegarde ajoutée : ' . $file);

        return Command::SUCCESS;
    }
}

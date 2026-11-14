<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserStatistic;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'UserStatisticCommand',
    description: 'Update user statistics',
    aliases    : ['statistic:user']
)]
class UserStatisticCommand extends Command
{
    const int MINUTES_PER_DAY = 1440;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct(AsCommand::class);
    }

    /**
     * À lancer au début de chaque heure : enregistre l'heure qui vient de s'écouler, puis le jour, le mois et l'année quand ils se terminent
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(["Update user statistics..."]);

        $period = new DateTime('- 1 hour');

        $this->saveStatistic($period->format('Y'), $period->format('m'), $period->format('d'), $period->format('H'), 60);

        if ($period->format('H') === '23') { // Dernière heure de la journée
            $this->saveStatistic($period->format('Y'), $period->format('m'), $period->format('d'), null, self::MINUTES_PER_DAY);

            if ($period->format('d') === $period->format('t')) { // Dernier jour du mois
                $this->saveStatistic($period->format('Y'), $period->format('m'), null, null, (int)$period->format('t') * self::MINUTES_PER_DAY);

                if ($period->format('m') === '12') { // Dernier mois de l'année
                    $this->saveStatistic($period->format('Y'), null, null, null, (365 + (int)$period->format('L')) * self::MINUTES_PER_DAY);
                }
            }
        }

        $this->entityManager->flush();

        $output->writeln(["Update user statistics finished"]);

        return Command::SUCCESS;
    }

    private function saveStatistic(string $year, ?string $month, ?string $day, ?string $hour, int $minutes): void
    {
        $criteria = ['year' => $year, 'month' => $month, 'day' => $day, 'hour' => $hour];

        if (!($userStatistic = $this->entityManager->getRepository(UserStatistic::class)->findOneBy($criteria)) instanceof UserStatistic) {
            $userStatistic = new UserStatistic()->setYear($year)->setMonth($month)->setDay($day)->setHour($hour);
        }

        $userStatistic->setNumberUsers($this->entityManager->getRepository(User::class)->getUsersOnline($minutes)[1]);

        $this->entityManager->persist($userStatistic);
    }
}

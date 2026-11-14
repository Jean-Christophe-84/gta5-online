<?php

namespace App\Helper;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class UserStatisticHelper
{
    const int MAX_DAYS_BY_DAY     = 31;
    const int MAX_MONTHS_BY_MONTH = 24;

    const array PERIODS = [
        'hour'  => ["CONCAT(year, '-', month, '-', day, ' ', hour)", 'hour IS NOT NULL', 'Y-m-d H', 'PT1H'],
        'day'   => ["CONCAT(year, '-', month, '-', day)", 'day IS NOT NULL AND hour IS NULL', 'Y-m-d', 'P1D'],
        'month' => ["CONCAT(year, '-', month)", 'month IS NOT NULL AND day IS NULL', 'Y-m', 'P1M'],
        'year'  => ['year', 'month IS NULL', 'Y', 'P1Y']
    ];

    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private RequestStack $requestStack
    ) {
    }

    public function getFilter(): array
    {
        $filter = $this->requestStack->getSession()->get('userStatistic');

        return ['start' => $filter['start'] ?? date('Y-m-d'), 'end' => $filter['end'] ?? date('Y-m-d')];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getStatistics(array $filter): array
    {
        $start = new DateTimeImmutable($filter['start']);
        $end   = new DateTimeImmutable($filter['end']);

        if ($start == $end) {
            $granularity = 'hour';
            $end         = $end->setTime(23, 0);
        } else if ($start->diff($end)->days < self::MAX_DAYS_BY_DAY) {
            $granularity = 'day';
        } else if (((int)$end->format('Y') * 12 + (int)$end->format('m')) - ((int)$start->format('Y') * 12 + (int)$start->format('m')) < self::MAX_MONTHS_BY_MONTH) {
            $granularity = 'month';
            $start       = $start->modify('first day of this month');
        } else {
            $granularity = 'year';
            $start       = $start->modify('first day of january this year');
        }

        [$key, $condition, $format, $duration] = self::PERIODS[$granularity];

        $query = "
            SELECT " . $key . " AS period, number_users AS numberUsers
            FROM user_statistic
            WHERE " . $condition . " AND " . $key . " BETWEEN :start AND :end
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('start', $start->format($format));
        $statement->bindValue('end', $end->format($format));

        $numberUsers = array_column($statement->executeQuery()->fetchAllAssociative(), 'numberUsers', 'period');
        $categories  = [];
        $data        = [];
        $interval    = new DateInterval($duration);
        $now         = new DateTime();

        for ($date = $start; $date <= $end; $date = $date->add($interval)) {
            $categories[] = match ($granularity) {
                'hour'  => $this->configurationHelper->getTranslateLocale('hour_range', ['start' => (int)$date->format('H'), 'end' => (int)$date->format('H') + 1]),
                'day'   => $this->configurationHelper->mbUcfirst($this->configurationHelper->getTranslateLocale('date_day_month', ['date' => $date])),
                'month' => $this->configurationHelper->mbUcfirst($this->configurationHelper->getTranslateLocale('date_month_year', ['date' => $date])),
                'year'  => $date->format('Y')
            };

            $data[] = $date->add($interval) <= $now ? (int)($numberUsers[$date->format($format)] ?? 0) : null;
        }

        return ['categories' => $categories, 'data' => $data];
    }
}

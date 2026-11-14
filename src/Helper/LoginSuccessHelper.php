<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class LoginSuccessHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack
    ) {
    }

    public function getFilter(bool $defaultFilter = false): array
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('loginSuccess')) {
            return ['search' => '', 'page' => 1, 'order' => ['login_success-date' => 'DESC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getLoginSuccesses(array $filter, string $count = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count !== '') {
            $query = "SELECT COUNT(login_success.id) AS count";
        } else {
            $query = "
                SELECT login_success.id, login_success.date, login_success.ip, user.firstname, user.lastname, user.username, user.mail
            ";
        }

        $query .= " FROM login_success INNER JOIN user ON user.id = login_success.user_id";

        foreach ($values as $key => $value) {
            $query .= ($key ? " AND" : " WHERE") . " (
                user.firstname LIKE :search_" . $key . " OR user.lastname LIKE :search_" . $key . " OR user.username LIKE :search_" . $key . "
                OR user.mail LIKE :search_" . $key . " OR login_success.ip LIKE :search_" . $key . "
            )";
        }

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($count) {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getLoginSuccesses($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getLoginSuccesses($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('loginSuccess', $filter);
        }

        return $filter;
    }
}

<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GroupGtaHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack, private Security $security
    ) {
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('groupGta')) {
            return ['search' => '', 'page' => 1, 'order' => ['group_gta_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getGroupGta(int $groupGtaId): array|int
    {
        $query = "
            SELECT group_gta.id, group_gta.default, group_gta_locale.name, group_gta_locale.description
            FROM group_gta
            LEFT JOIN group_gta_locale ON group_gta.id = group_gta_locale.group_gta_id AND group_gta_locale.locale_id = :localeId
            WHERE group_gta.id = :groupGtaId
            GROUP BY group_gta.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('groupGtaId', $groupGtaId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getGroupGtas(array $filter, string $count = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count !== '') {
            $query = "SELECT COUNT(DISTINCT group_gta.id) AS count";
        } else {
            $query = "SELECT group_gta.id, group_gta_locale.name, group_gta_locale.description";
        }

        $query .= "
            FROM group_gta
            LEFT JOIN group_gta_locale ON group_gta.id = group_gta_locale.group_gta_id AND group_gta_locale.locale_id = :localeId
        ";

        foreach ($values as $key => $value) {
            if ($key) {
                $query .= "
                        AND (
                            group_gta_locale.name LIKE :search_" . $key . "
                            OR group_gta_locale.description LIKE :search_" . $key . "
                        )
                    ";
            } else {
                $query .= "
                        WHERE (
                            group_gta_locale.name LIKE :search_" . $key . "
                            OR group_gta_locale.description LIKE :search_" . $key . "
                        )
                    ";
            }
        }

        $query .= $count !== '' ? "" : " GROUP BY group_gta.id";

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

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
        return $this->configurationHelper->formatNumber($this->getGroupGtas($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getGroupGtas($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('groupGta', $filter);
        }

        return $filter;
    }
}

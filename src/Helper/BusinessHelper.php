<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class BusinessHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack, private Security $security
    ) {
    }

    /**
     * Supprime l'élément avec ses données propres : à n'appeler que si getDeletion() ne renvoie aucun blocage
     *
     * @throws DoctrineDBALException
     */
    public function delete(int $businessId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM business_locale WHERE business_id = :id",
                "DELETE FROM business WHERE id = :id"
            ],
            $businessId
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusiness(int $businessId): array|int
    {
        $query = "
            SELECT business.id AS id, business_locale.name, business_locale.contact, title_update.id AS titleUpdateId, title_update.name AS titleUpdateName,
                   property_type.id AS propertyTypeId, property_type_locale.name AS propertyTypeName, business.maximum_number AS maximumNumber,
                   business_locale.daily_earnings AS dailyEarnings, business_locale.daily_expenses AS dailyExpenses, business_locale.improvement
            FROM business
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN title_update ON title_update.id = business.title_update_id
            LEFT JOIN property_type ON property_type.id = business.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            WHERE business.id = :businessId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('businessId', $businessId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusinesses(array $filter, string $count = ''): array|int
    {
        $perPage   = $this->parameterBag->get('per_page');
        $values    = $filter['search'] ? explode(' ', $filter['search']) : [];
        $hasValues = false;

        if ($count !== '') {
            $query = "SELECT COUNT(DISTINCT business.id) AS count";
        } else {
            $query = "
                SELECT business.id AS id, business_locale.name, business_locale.contact, title_update.name AS titleUpdateName,
                property_type_locale.name AS propertyTypeName, business.maximum_number AS maximumNumber, business_locale.daily_earnings AS dailyEarnings,
                business_locale.daily_expenses AS dailyExpenses, business_locale.improvement
            ";
        }

        $query .= "
            FROM business
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN title_update ON title_update.id = business.title_update_id
            LEFT JOIN property_type ON property_type.id = business.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
        ";

        if ($values !== []) {
            foreach ($values as $key => $value) {
                if ($key) {
                    $query .= "
                        AND (
                            business_locale.name LIKE :search_" . $key . "
                            OR property_type_locale.name LIKE :search_" . $key . "
                            OR business_locale.contact LIKE :search_" . $key . "
                            OR business.maximum_number LIKE :search_" . $key . "
                            OR title_update.name LIKE :search_" . $key . "
                            OR business_locale.daily_earnings LIKE :search_" . $key . "
                            OR business_locale.daily_expenses LIKE :search_" . $key . "
                            OR business_locale.improvement LIKE :search_" . $key . "
                        )
                    ";
                } else {
                    $query .= "
                        WHERE (
                            business_locale.name LIKE :search_" . $key . "
                            OR property_type_locale.name LIKE :search_" . $key . "
                            OR business_locale.contact LIKE :search_" . $key . "
                            OR business.maximum_number LIKE :search_" . $key . "
                            OR title_update.name LIKE :search_" . $key . "
                            OR business_locale.daily_earnings LIKE :search_" . $key . "
                            OR business_locale.daily_expenses LIKE :search_" . $key . "
                            OR business_locale.improvement LIKE :search_" . $key . "
                        )
                    ";
                }
            }

            $hasValues = true;
        }

        if ($filter['titleUpdateId'] !== 0) {
            $query     .= ($hasValues) ? " AND title_update.id = :titleUpdateId" : " WHERE title_update.id = :titleUpdateId";
            $hasValues = true;
        }

        if ($filter['propertyTypeId'] !== 0) {
            $query .= ($hasValues) ? " AND property_type.id = :propertyTypeId" : " WHERE property_type.id = :propertyTypeId";
        }

        $query .= $count !== '' ? "" : " GROUP BY business.id";

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($filter['propertyTypeId'] !== 0) {
            $statement->bindValue('propertyTypeId', $filter['propertyTypeId']);
        }

        if ($count !== '') {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $businessId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'business_goods' => "SELECT NULL FROM business_good WHERE business_good.business_id = :id",
                    'properties' => "SELECT property.name FROM property WHERE property.business_id = :id ORDER BY property.name"
                ],
                $businessId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('business')) {
            return ['titleUpdateId' => 0, 'propertyTypeId' => 0, 'search' => '', 'page' => 1, 'order' => ['business_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyTypeIds(array $filter): array|int
    {
        $query = "
            SELECT property_type_locale.name, property_type.id, COUNT(DISTINCT business.id) AS numberBusinesses
            FROM business
            LEFT JOIN property_type ON property_type.id = business.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id =:localeId
            LEFT JOIN title_update ON title_update.id = business.title_update_id
            WHERE property_type.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'PropertyTypeId') . " GROUP BY property_type.id ORDER BY property_type_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        $this->addValues($statement, $filter, 'PropertyTypeId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterTitleUpdateIds(array $filter): array|int
    {
        $query = "
            SELECT title_update.name, title_update.id, COUNT(DISTINCT business.id) AS numberBusinesses
            FROM business
            LEFT JOIN property_type ON property_type.id = business.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id =:localeId
            LEFT JOIN title_update ON title_update.id = business.title_update_id
            WHERE title_update.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'TitleUpdateId') . " GROUP BY title_update.id ORDER BY title_update.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'TitleUpdateId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getBusinesses($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function addValues($statement, array $filter, string $type): void
    {
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($type !== 'PropertyTypeId' && $filter['propertyTypeId'] !== 0) {
            $statement->bindValue('propertyTypeId', $filter['propertyTypeId']);
        }
    }

    private function getQuery(array $filter, string $type, bool $verifyValues = false): string
    {
        $query     = '';
        $hasValues = !$verifyValues;

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            if ($hasValues) {
                $query .= " AND title_update.id = :titleUpdateId";
            } else {
                $query .= " WHERE title_update.id = :titleUpdateId";
            }
        }

        if ($type !== 'PropertyTypeId' && $filter['propertyTypeId'] !== 0) {
            if ($hasValues) {
                $query .= " AND property_type.id = :propertyTypeId";
            } else {
                $query .= " WHERE property_type.id = :propertyTypeId";
            }
        }

        return $query;
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getBusinesses($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('business', $filter);
        }

        return $filter;
    }
}

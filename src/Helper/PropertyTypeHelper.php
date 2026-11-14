<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class PropertyTypeHelper
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
    public function delete(int $propertyTypeId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM property_type_locale WHERE property_type_id = :id",
                "DELETE FROM property_type_storage_location WHERE property_type_id = :id",
                "DELETE FROM property_type WHERE id = :id"
            ],
            $propertyTypeId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $propertyTypeId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'businesses' => "SELECT COALESCE(business_locale.name, business.id) FROM business LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId WHERE business.property_type_id = :id ORDER BY 1",
                    'properties' => "SELECT property.name FROM property WHERE property.property_type_id = :id ORDER BY property.name"
                ],
                $propertyTypeId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('propertyType')) {
            return ['storageLocationIds' => [], 'search' => '', 'page' => 1, 'order' => ['property_type_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterStorageLocations(array $filter): array|int
    {
        $values = $filter['search'] ? explode(' ', $filter['search']) : [];

        $query = "
            SELECT storage_location_locale.name, storage_location.id, COUNT(property_type.id) AS numberPropertyTypes
            FROM property_type
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN storage_location ON storage_location.id = property_type_storage_location.storage_location_id
            LEFT JOIN storage_location_locale ON storage_location_locale.storage_location_id = storage_location.id
                AND storage_location_locale.locale_id = :localeId
            WHERE storage_location.id IS NOT NULL
        ";

        foreach ($values as $key => $value) {
            $query .= " AND property_type_locale.name LIKE :search_" . $key;
        }

        $query .= " GROUP BY storage_location.id ORDER BY storage_location_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPropertyType(int $propertyTypeId): array|int
    {
        $query = "
            SELECT property_type.id, property_type_locale.name,
                   GROUP_CONCAT(DISTINCT storage_location.id ORDER BY storage_location_locale.name ASC SEPARATOR '+') AS storageLocationIds
            FROM property_type
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN storage_location ON storage_location.id = property_type_storage_location.storage_location_id
            LEFT JOIN storage_location_locale ON storage_location_locale.storage_location_id = storage_location.id
                AND storage_location_locale.locale_id = :localeId
            WHERE property_type.id = :propertyTypeId
            GROUP BY property_type.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('propertyTypeId', $propertyTypeId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPropertyTypes(array $filter, bool $count = false): array|int
    {
        $perPage   = $this->parameterBag->get('per_page');
        $values    = $filter['search'] ? explode(' ', $filter['search']) : [];
        $hasValues = false;

        if ($count) {
            $query = "SELECT COUNT(DISTINCT property_type.id) AS count";
        } else {
            $query = "
                SELECT property_type.id, property_type_locale.name,
                       GROUP_CONCAT(DISTINCT storage_location_locale.name ORDER BY storage_location_locale.name ASC SEPARATOR '+') AS storageLocationNames
            ";
        }

        $query .= "
            FROM property_type
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN storage_location ON storage_location.id = property_type_storage_location.storage_location_id
            LEFT JOIN storage_location_locale ON storage_location_locale.storage_location_id = storage_location.id
                AND storage_location_locale.locale_id = :localeId
            WHERE property_type.id IN (
                SELECT DISTINCT property_type_search.id
                FROM property_type AS property_type_search
            LEFT JOIN property_type_locale property_type_locale_search
                    ON property_type_locale_search.property_type_id = property_type_search.id AND property_type_locale_search.locale_id = :localeId
                LEFT JOIN property_type_storage_location property_type_storage_location_search
                    ON property_type_storage_location_search.property_type_id = property_type_search.id
                LEFT JOIN storage_location storage_location_search ON storage_location_search.id = property_type_storage_location_search.storage_location_id
                LEFT JOIN storage_location_locale storage_location_search_locale
                    ON storage_location_search_locale.storage_location_id = storage_location_search.id AND storage_location_search_locale.locale_id = :localeId
        ";

        if ($values !== []) {
            foreach ($values as $key => $value) {
                if ($key) {
                    $query .= " AND (property_type_locale_search.name LIKE :search_" . $key . " OR storage_location_search_locale.name LIKE :search_" . $key . ")";
                } else {
                    $query .= " WHERE (property_type_locale_search.name LIKE :search_" . $key . " OR storage_location_search_locale.name LIKE :search_" . $key . ")";
                }
            }

            $hasValues = true;
        }

        if (!empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                if ($key) {
                    $query .= " OR property_type_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (property_type_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                    } else {
                        $query .= " WHERE (property_type_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                    }
                }
            }

            $query .= ")";
        }

        $query .= $count ? ")" : ") GROUP BY property_type.id";

        if (!$count && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if (!empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                $statement->bindValue('storageLocationId_' . $key, $storageLocationId);
            }
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
        return $this->configurationHelper->formatNumber($this->getPropertyTypes($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getPropertyTypes($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('propertyType', $filter);
        }

        return $filter;
    }
}

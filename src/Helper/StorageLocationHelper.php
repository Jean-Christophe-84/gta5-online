<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class StorageLocationHelper
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
    public function delete(int $storageLocationId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM storage_location_locale WHERE storage_location_id = :id",
                "DELETE FROM storage_location WHERE id = :id"
            ],
            $storageLocationId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $storageLocationId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'property_types' => "SELECT COALESCE(property_type_locale.name, property_type.id) FROM property_type_storage_location INNER JOIN property_type ON property_type.id = property_type_storage_location.property_type_id LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId WHERE property_type_storage_location.storage_location_id = :id ORDER BY 1",
                    'vehicles' => "SELECT vehicle.name FROM vehicle_storage_location INNER JOIN vehicle ON vehicle.id = vehicle_storage_location.vehicle_id WHERE vehicle_storage_location.storage_location_id = :id ORDER BY vehicle.name"
                ],
                $storageLocationId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('storageLocation')) {
            return ['search' => '', 'page' => 1, 'order' => ['storage_location_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getStorageLocation(int $storageLocationId): array|int
    {
        $query = "
            SELECT storage_location_locale.storage_location_id AS id, storage_location_locale.name, storage_location.property_required AS propertyRequired
            FROM storage_location_locale
            INNER JOIN storage_location ON storage_location.id = storage_location_locale.storage_location_id
            WHERE storage_location_locale.storage_location_id = :storageLocationId AND storage_location_locale.locale_id = :localeId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('storageLocationId', $storageLocationId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getStorageLocations(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT storage_location_locale.storage_location_id) AS count";
        } else {
            $query = "SELECT storage_location_locale.storage_location_id AS id, storage_location_locale.name";
        }

        $query .= " FROM storage_location_locale WHERE storage_location_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND storage_location_locale.name LIKE :search_" . $key;
        }

        if (!$count && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

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
        return $this->configurationHelper->formatNumber($this->getStorageLocations($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getStorageLocations($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('storageLocation', $filter);
        }

        return $filter;
    }
}

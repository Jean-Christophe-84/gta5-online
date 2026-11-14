<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class VehicleManufacturerHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack
    ) {
    }

    /**
     * Supprime l'élément avec ses données propres : à n'appeler que si getDeletion() ne renvoie aucun blocage
     *
     * @throws DoctrineDBALException
     */
    public function delete(int $vehicleManufacturerId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM vehicle_manufacturer WHERE id = :id"
            ],
            $vehicleManufacturerId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $vehicleManufacturerId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'vehicles' => "SELECT vehicle.name FROM vehicle WHERE vehicle.vehicle_manufacturer_id = :id ORDER BY vehicle.name"
                ],
                $vehicleManufacturerId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('vehicleManufacturer')) {
            return ['search' => '', 'page' => 1, 'order' => ['vehicle_manufacturer-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getVehicleManufacturers($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicleManufacturer(int $vehicleManufacturerId): array|int
    {
        $query = "
            SELECT vehicle_manufacturer.id, vehicle_manufacturer.name, vehicle_manufacturer.real_name AS realName
            FROM vehicle_manufacturer
            WHERE vehicle_manufacturer.id = :vehicleManufacturerId
            GROUP BY vehicle_manufacturer.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('vehicleManufacturerId', $vehicleManufacturerId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicleManufacturers(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT vehicle_manufacturer.id) AS count";
        } else {
            $query = "SELECT vehicle_manufacturer.id, vehicle_manufacturer.name, vehicle_manufacturer.real_name AS realName";
        }

        $query .= " FROM vehicle_manufacturer";

        foreach ($values as $key => $value) {
            $query .= $key ? " AND vehicle_manufacturer.name LIKE :search_" . $key : " WHERE vehicle_manufacturer.name LIKE :search_" . $key;
        }

        $query .= $count ? "" : " GROUP BY vehicle_manufacturer.id";

        if (!$count && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

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
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getVehicleManufacturers($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('vehicleManufacturer', $filter);
        }

        return $filter;
    }
}

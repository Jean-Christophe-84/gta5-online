<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class VehicleAcquisitionHelper
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
    public function delete(int $vehicleAcquisitionId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM vehicle_acquisition_locale WHERE vehicle_acquisition_id = :id",
                "DELETE FROM vehicle_acquisition WHERE id = :id"
            ],
            $vehicleAcquisitionId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $vehicleAcquisitionId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'vehicles' => "SELECT vehicle.name FROM vehicle_vehicle_acquisition INNER JOIN vehicle ON vehicle.id = vehicle_vehicle_acquisition.vehicle_id WHERE vehicle_vehicle_acquisition.vehicle_acquisition_id = :id ORDER BY vehicle.name"
                ],
                $vehicleAcquisitionId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('vehicleAcquisition')) {
            return ['search' => '', 'page' => 1, 'order' => ['vehicle_acquisition_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getVehicleAcquisitions($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicleAcquisition(int $vehicleAcquisitionId): array|int
    {
        $query = "
            SELECT vehicle_acquisition_locale.vehicle_acquisition_id AS id, vehicle_acquisition_locale.name, vehicle_acquisition.acquirable,
                   vehicle_acquisition.available, vehicle_acquisition.promotion
            FROM vehicle_acquisition_locale
            INNER JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_acquisition_locale.vehicle_acquisition_id
            WHERE vehicle_acquisition_locale.vehicle_acquisition_id = :vehicleAcquisitionId AND vehicle_acquisition_locale.locale_id = :localeId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('vehicleAcquisitionId', $vehicleAcquisitionId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicleAcquisitions(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT vehicle_acquisition_locale.vehicle_acquisition_id) AS count";
        } else {
            $query = "SELECT vehicle_acquisition_locale.vehicle_acquisition_id AS id, vehicle_acquisition_locale.name";
        }

        $query .= " FROM vehicle_acquisition_locale WHERE vehicle_acquisition_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND vehicle_acquisition_locale.name LIKE :search_" . $key;
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
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getVehicleAcquisitions($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('vehicleAcquisition', $filter);
        }

        return $filter;
    }
}

<?php

namespace App\Helper;

use App\Entity\Vehicle;
use App\Entity\VehicleClass;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class VehicleHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private PropertyHelper      $propertyHelper, private RequestStack $requestStack, private Security $security
    ) {
    }

    /**
     * Supprime l'élément avec ses données propres : à n'appeler que si getDeletion() ne renvoie aucun blocage
     *
     * @throws DoctrineDBALException
     */
    public function delete(int $vehicleId): void
    {
        $path = $this->entityManager->getConnection()->fetchOne("SELECT path FROM vehicle WHERE id = :id", ['id' => $vehicleId]);

        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM vehicle_locale WHERE vehicle_id = :id",
                "DELETE FROM vehicle_delivery_method WHERE vehicle_id = :id",
                "DELETE FROM vehicle_modification_location WHERE vehicle_id = :id",
                "DELETE FROM vehicle_storage_location WHERE vehicle_id = :id",
                "DELETE FROM vehicle_vehicle WHERE vehicle_two_id = :id",
                "DELETE FROM vehicle_vehicle_acquisition WHERE vehicle_id = :id",
                "DELETE FROM vehicle_vehicle_class WHERE vehicle_id = :id",
                "DELETE FROM vehicle_user WHERE vehicle_id = :id",
                "DELETE FROM vehicle WHERE id = :id"
            ],
            $vehicleId
        );

        $this->configurationHelper->removeUpload('vehicle', $path ? : null);
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $vehicleId): array
    {
        $warnings = [];

        foreach ([
            'deletion_removed_from_users' => "SELECT COUNT(*) FROM vehicle_user WHERE vehicle_id = :id AND owned = 1"
        ] as $key => $query) {
            if ($count = (int)$this->entityManager->getConnection()->fetchOne($query, ['id' => $vehicleId])) {
                $warnings[] = $this->configurationHelper->getTranslateLocale($key, ['count' => $count]);
            }
        }

        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'child_vehicles' => "SELECT vehicle.name FROM vehicle_vehicle INNER JOIN vehicle ON vehicle.id = vehicle_vehicle.vehicle_two_id WHERE vehicle_vehicle.vehicle_one_id = :id ORDER BY vehicle.name",
                    'casino_drivers' => "SELECT DISTINCT heist_casino_driver.name FROM heist_casino_driver_vehicle INNER JOIN heist_casino_driver ON heist_casino_driver.id = heist_casino_driver_vehicle.heist_casino_driver_id WHERE heist_casino_driver_vehicle.vehicle_id = :id ORDER BY heist_casino_driver.name"
                ],
                $vehicleId
            ),
            'warnings' => $warnings
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getEvolutionLabelVehicle(int $evolution): string
    {
        $filter = match ($evolution) {
            Vehicle::FILTER_EVOLUTION_ALL             => 'all',
            Vehicle::FILTER_EVOLUTION_YES             => 'yes',
            Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS => 'yes_with_others',
            Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS  => 'no_with_others',
            Vehicle::FILTER_EVOLUTION_NO              => 'no',
            default                                   => null,
        };

        if ($filter === null) {
            return '';
        }

        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('filter_evolution', ['entity' => 'vehicles', 'filter' => $filter])
        );
    }

    public function getFilter(bool $defaultFilter = false)
    {
        $default = [
            'vehicleManufacturerId'    => 0,
            'titleUpdateId'            => 0,
            'owned'                    => null,
            'propertyId'               => 0,
            'wantedPropertyId'         => 0,
            'seats'                    => 0,
            'vehicleClassIds'          => [],
            'vehicleAcquisitionIds'    => [],
            'storageLocationIds'       => [],
            'search'                   => '',
            'vehicleTwo'               => 0,
            'optimized'                => null,
            'evolution'                => 0,
            'wantedProperty'           => null,
            'notVehicleClassIds'       => [],
            'notVehicleAcquisitionIds' => [],
            'notStorageLocationIds'    => [],
            'page'                     => 1,
            'order'                    => ['vehicle-name' => 'ASC']
        ];
        $filter  = $this->requestStack->getSession()->get('vehicle');

        // @note: a filter saved before a key was renamed is dropped, its stale keys would break the query
        return $defaultFilter || !$filter || array_diff_key($default, $filter) ? $default : $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterEvolutions(array $filter, int $evolution): array
    {
        $query = "
            SELECT COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
        ";

        switch ($evolution) {
            case Vehicle::FILTER_EVOLUTION_YES:
                $query .= " WHERE vehicle.evolution = 1";
                break;
            case Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS:
                $query .= " WHERE vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                break;
            case Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS:
                $query .= " WHERE vehicle.evolution = 0";
                break;
            case Vehicle::FILTER_EVOLUTION_NO:
                $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                break;
        }

        $query .= $this->getQuery($filter, 'Evolution');

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'Evolution');

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterNotStorageLocationIds(array $filter): array|int
    {
        $query = "
            SELECT storage_location_locale.name, storage_location.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE storage_location.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'NotStorageLocationId') . " GROUP BY storage_location.id ORDER BY storage_location_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'NotStorageLocationId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterNotVehicleAcquisitionIds(array $filter): array|int
    {
        $query = "
            SELECT vehicle_acquisition_locale.name, vehicle_acquisition.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle_acquisition.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'NotVehicleAcquisitionId') . " GROUP BY vehicle_acquisition.id ORDER BY vehicle_acquisition_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'NotVehicleAcquisitionId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterNotVehicleClassIds(array $filter): array|int
    {
        $query = "
            SELECT vehicle_class_locale.name, vehicle_class.id, COUNT(DISTINCT vehicle.id) AS numberVehicles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle_class.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'NotVehicleClassId') . " GROUP BY vehicle_class.id ORDER BY vehicle_class_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'NotVehicleClassId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterOptimizeds(array $filter): array
    {
        $query = "
            SELECT vehicle.optimized, COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'Optimized', true) . " GROUP BY vehicle.optimized ORDER BY vehicle.optimized";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'Optimized');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterOwneds(array $filter): array
    {
        $query = "
            SELECT vehicle_user.owned, COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'Owned', true) . " GROUP BY vehicle_user.owned ORDER BY vehicle_user.owned";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'Owned');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyIds(array $filter): array
    {
        $query = "
            SELECT property.id,
                   IF(property.id IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property), CONCAT('--- ', property.name), property.name) AS name,
                   COALESCE(property.capacity_vehicles, 0) AS capacityVehicles, COALESCE(property.capacity_bicycles, 0) AS capacityBicycles,
                   property.property_type_id AS propertyTypeId,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_two.id ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE property_user.owned = 1
            AND property.id NOT IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property)
        ";

        $query .= $this->getQuery($filter, 'PropertyId') . " GROUP BY property.id ORDER BY property.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'PropertyId');

        if (!empty($properties = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($properties as $key => $property) {
                $properties[$key]['propertyTwos']   = [];
                $properties[$key]['propertyTwoIds'] = $properties[$key]['propertyTwoIds'] ? explode('+', $properties[$key]['propertyTwoIds']) : [];

                foreach ($properties[$key]['propertyTwoIds'] as $propertyTwoId) {
                    $properties[$key]['propertyTwos'][] = $this->propertyHelper->getProperty($propertyTwoId);
                }

                unset($properties[$key]['propertyTwoIds']);

                if (empty($properties[$key]['propertyTwos']) && $properties[$key]['capacityVehicles'] == 0 && $properties[$key]['capacityBicycles'] == 0) {
                    unset($properties[$key]);
                }
            }
        }

        return $properties;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterSeats(array $filter): array|int
    {
        $query = "
            SELECT vehicle.seats, COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle.seats IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'Seat') . " GROUP BY vehicle.seats ORDER BY vehicle.seats";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'Seat');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterStorageLocationIds(array $filter): array|int
    {
        $query = "
            SELECT storage_location_locale.name, storage_location.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE storage_location.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'StorageLocationId') . " GROUP BY storage_location.id ORDER BY storage_location_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'StorageLocationId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterTitleUpdateIds(array $filter): array|int
    {
        $query = "
            SELECT title_update.name, title_update.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE title_update.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'TitleUpdateId') . " GROUP BY title_update.id ORDER BY title_update.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'TitleUpdateId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterVehicleAcquisitionIds(array $filter): array|int
    {
        $query = "
            SELECT vehicle_acquisition_locale.name, vehicle_acquisition.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle_acquisition.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'AcquisitionId') . " GROUP BY vehicle_acquisition.id ORDER BY vehicle_acquisition_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'AcquisitionId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterVehicleClassIds(array $filter): array|int
    {
        $query = "
            SELECT vehicle_class_locale.name, vehicle_class.id, COUNT(DISTINCT vehicle.id) AS numberVehicles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle_class.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'VehicleClassId') . " GROUP BY vehicle_class.id ORDER BY vehicle_class_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'VehicleClassId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterVehicleManufacturerIds(array $filter): array|int
    {
        $query = "
            SELECT vehicle_manufacturer.name, vehicle_manufacturer.real_name AS realName, vehicle_manufacturer.id,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle_manufacturer.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'VehicleManufacturerId') . " GROUP BY vehicle_manufacturer.id ORDER BY vehicle_manufacturer.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'VehicleManufacturerId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterVehicleTwos(array $filter, int $vehicleTwo): array
    {
        $query = "
            SELECT COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
        ";

        switch ($vehicleTwo) {
            case Vehicle::FILTER_VEHICLE_TWO_YES:
                $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                break;
            case Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS:
                $query .= " WHERE (vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) OR vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle))";
                break;
            case Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS:
                $query .= " WHERE vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                break;
            case Vehicle::FILTER_VEHICLE_TWO_NO:
                $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                break;
        }

        $query .= $this->getQuery($filter, 'VehicleTwo');

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'VehicleTwo');

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterWantedProperties(array $filter): array
    {
        $query = "
            SELECT IF(vehicle_user.wanted_property_id IS NOT NULL, 1, 0) AS wantedProperty,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles
            FROM vehicle
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'WantedProperty', true) . " GROUP BY wantedProperty ORDER BY wantedProperty";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'WantedProperty');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterWantedPropertyIds(array $filter): array
    {
        $query = "
            SELECT property.id,
                   IF(property.id IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property), CONCAT('--- ', property.name), property.name) AS name,
                   COALESCE(property.capacity_vehicles, 0) AS capacityVehicles, COALESCE(property.capacity_bicycles, 0) AS capacityBicycles,
                   property.property_type_id AS propertyTypeId,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_two.id ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.wanted_property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE property_user.wanted = 1
            AND property.id NOT IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property)
        ";

        $query .= $this->getQuery($filter, 'WantedPropertyId') . " GROUP BY property.id ORDER BY property.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);

        $this->addValues($statement, $filter, 'WantedPropertyId');

        if (!empty($properties = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($properties as $key => $property) {
                $properties[$key]['propertyTwos']   = [];
                $properties[$key]['propertyTwoIds'] = $properties[$key]['propertyTwoIds'] ? explode('+', $properties[$key]['propertyTwoIds']) : [];

                foreach ($properties[$key]['propertyTwoIds'] as $propertyTwoId) {
                    $properties[$key]['propertyTwos'][] = $this->propertyHelper->getProperty($propertyTwoId);
                }

                unset($properties[$key]['propertyTwoIds']);

                if (empty($properties[$key]['propertyTwos']) && $properties[$key]['capacityVehicles'] == 0 && $properties[$key]['capacityBicycles'] == 0) {
                    unset($properties[$key]);
                }
            }
        }

        return $properties;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getNameAndAverageVehicle(int $vehicleId): string
    {
        $query = "
            SELECT vehicle.name,
                (
                    (
                        COALESCE(vehicle.speed, 0) +
                        COALESCE(vehicle.acceleration, 0) +
                        COALESCE(vehicle.braking, 0) +
                        COALESCE(vehicle.handling, 0)
                    ) * 1.0
                ) / (
                    (IF(vehicle.speed IS NOT NULL, 1, 0)) +
                    (IF(vehicle.acceleration IS NOT NULL, 1, 0)) +
                    (IF(vehicle.braking IS NOT NULL, 1, 0)) +
                    (IF(vehicle.handling IS NOT NULL, 1, 0))
                ) AS average
            FROM vehicle
            WHERE vehicle.id = :vehicleId
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('vehicleId', $vehicleId);

        return $statement->executeQuery()->fetchAssociative()['name'] . ' (' . $this->configurationHelper->formatPercent($statement->executeQuery()->fetchAssociative()['average'] ?? 0, 2) . ')';
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getParameters(array $filter): array
    {
        return [
            'filter'                         => $filter,
            'filterEvolutions'               => array_map(
                fn(int $evolution): array => ['evolution' => $evolution] + $this->getFilterEvolutions($filter, $evolution),
                [
                    Vehicle::FILTER_EVOLUTION_YES,
                    Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS,
                    Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS,
                    Vehicle::FILTER_EVOLUTION_NO
                ]
            ),
            'filterOwneds'                   => $this->getFilterOwneds($filter),
            'filterNotStorageLocationIds'    => $this->getFilterNotStorageLocationIds($filter),
            'filterNotVehicleAcquisitionIds' => $this->getFilterNotVehicleAcquisitionIds($filter),
            'filterNotVehicleClassIds'       => $this->getFilterNotVehicleClassIds($filter),
            'filterOptimizeds'               => $this->getFilterOptimizeds($filter),
            'filterPropertyIds'              => $this->getFilterPropertyIds($filter),
            'filterWantedProperties'         => $this->getFilterWantedProperties($filter),
            'filterWantedPropertyIds'        => $this->getFilterWantedPropertyIds($filter),
            'filterSeats'                    => $this->getFilterSeats($filter),
            'filterStorageLocationIds'       => $this->getFilterStorageLocationIds($filter),
            'filterTitleUpdateIds'           => $this->getFilterTitleUpdateIds($filter),
            'filterVehicleAcquisitionIds'    => $this->getFilterVehicleAcquisitionIds($filter),
            'filterVehicleClassIds'          => $this->getFilterVehicleClassIds($filter),
            'filterVehicleManufacturerIds'   => $this->getFilterVehicleManufacturerIds($filter),
            'filterVehicleTwos'              => array_map(
                fn(int $vehicleTwo): array => ['vehicleTwo' => $vehicleTwo] + $this->getFilterVehicleTwos($filter, $vehicleTwo),
                [
                    Vehicle::FILTER_VEHICLE_TWO_YES,
                    Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS,
                    Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS,
                    Vehicle::FILTER_VEHICLE_TWO_NO
                ]
            ),
            'properties'                     => $this->getProperties(),
            'wantedProperties'               => $this->getWantedProperties(),
            'summary'                        => $this->security->isGranted(Vehicle::PERMISSION_VEHICLE_OWN) ? $this->getSummary($filter) : null,
            'totalNames'                     => $this->getTotalNames(),
            'vehicles'                       => $this->getVehicles($filter)
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPlaces(): array
    {
        $query = "
            SELECT COALESCE(SUM(property.capacity_vehicles), 0) AS placesCapacity,
                   (
                       SELECT COUNT(DISTINCT stored_vehicle_user.vehicle_id)
                       FROM vehicle_user stored_vehicle_user
                       INNER JOIN property_user stored_property_user
                           ON stored_property_user.property_id = stored_vehicle_user.property_id AND stored_property_user.user_id = :userId
                           AND stored_property_user.owned = 1
                       WHERE stored_vehicle_user.user_id = :userId AND stored_vehicle_user.owned = 1
                       AND NOT EXISTS (
                           SELECT 1 FROM vehicle_vehicle_class bicycle
                           WHERE bicycle.vehicle_id = stored_vehicle_user.vehicle_id AND bicycle.vehicle_class_id = :bicycleClassId
                       )
                   ) AS placesStored
            FROM property
            INNER JOIN property_user ON property_user.property_id = property.id AND property_user.user_id = :userId AND property_user.owned = 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);
        $statement->bindValue('userId', $this->security->getUser()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getProperties(): array
    {
        $query = "
            SELECT property.id, property.name, COALESCE(property.capacity_vehicles, 0) AS capacityVehicles,
                   COALESCE(property.capacity_bicycles, 0) AS capacityBicycles, property.property_type_id AS propertyTypeId,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_two.id ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            WHERE property_user.owned = 1 AND property.id NOT IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property)
            GROUP BY property.id
            ORDER BY property.name
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if (!empty($properties = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($properties as $key => $property) {
                $properties[$key]['propertyTwos']   = [];
                $properties[$key]['propertyTwoIds'] = $properties[$key]['propertyTwoIds'] ? explode('+', $properties[$key]['propertyTwoIds']) : [];

                foreach ($properties[$key]['propertyTwoIds'] as $propertyTwoId) {
                    $properties[$key]['propertyTwos'][] = $this->propertyHelper->getProperty($propertyTwoId);
                }

                unset($properties[$key]['propertyTwoIds']);
            }
        }

        return $properties;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getSummary(array $filter): array
    {
        return array_merge($this->getVehicles($filter, '', 'summary'), $this->getPlaces());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getVehicles($this->getFilter($defaultFilter), 'id'));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getUrls(): array
    {
        $query = "SELECT vehicle.url FROM vehicle WHERE vehicle.url IS NOT NULL";

        $statement = $this->entityManager->getConnection()->prepare($query);

        return $statement->executeQuery()->fetchFirstColumn();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicle(int $vehicleId): array|false
    {
        $query = "
            SELECT vehicle.id, vehicle.url, vehicle.real_life_vehicle AS realLifeVehicle, vehicle.name, vehicle_manufacturer.id AS vehicleManufacturerId,
                   vehicle_manufacturer.name AS vehicleManufacturerName, vehicle_user.owned, property.id AS propertyId,
                   wanted_property.id AS wantedPropertyId, wanted_property.name AS wantedPropertyName,
                   GROUP_CONCAT(DISTINCT vehicle_one.id ORDER BY vehicle_vehicle.position ASC SEPARATOR '+') AS vehicleOneIds,
                   GROUP_CONCAT(DISTINCT vehicle_one.name ORDER BY vehicle_vehicle.position ASC SEPARATOR '+') AS vehicleOneNames,
                   GROUP_CONCAT(DISTINCT vehicle_class.id ORDER BY vehicle_vehicle_class.position ASC SEPARATOR '+') AS vehicleClassIds,
                   GROUP_CONCAT(DISTINCT vehicle_class_locale.name ORDER BY vehicle_vehicle_class.position ASC SEPARATOR '+') AS vehicleClassNames,
                   vehicle_drivetrain.id AS vehicleDrivetrainId, title_update.id AS titleUpdateId, vehicle.price, vehicle.discounted_price AS discountedPrice,
                   vehicle.discounted_percentage AS discountedPercentage, vehicle.top_speed_kilometers AS topSpeedKilometers, vehicle.top_speed_miles AS topSpeedMiles,
                   vehicle.seats, vehicle.weight, vehicle.gears, vehicle.top_speed_kilometers_maximum AS topSpeedKilometersMaximum,
                   vehicle.top_speed_miles_maximum AS topSpeedMilesMaximum, vehicle.speed, vehicle.acceleration, vehicle.braking,
                   vehicle.handling, GROUP_CONCAT(DISTINCT vehicle_acquisition.id ORDER BY vehicle_vehicle_acquisition.position ASC SEPARATOR '+') AS vehicleAcquisitionIds,
                   GROUP_CONCAT(DISTINCT vehicle_acquisition_locale.name ORDER BY vehicle_vehicle_acquisition.position ASC SEPARATOR '+') AS vehicleAcquisitionNames,
                   GROUP_CONCAT(DISTINCT storage_location.id ORDER BY vehicle_storage_location.position ASC SEPARATOR '+') AS storageLocationIds,
                   GROUP_CONCAT(DISTINCT storage_location_locale.name ORDER BY vehicle_storage_location.position ASC SEPARATOR '+') AS storageLocationNames,
                   GROUP_CONCAT(DISTINCT delivery_method.id ORDER BY vehicle_delivery_method.position ASC SEPARATOR '+') AS deliveryMethodIds,
                   GROUP_CONCAT(DISTINCT delivery_method_locale.name ORDER BY vehicle_delivery_method.position ASC SEPARATOR '+') AS deliveryMethodNames,
                   GROUP_CONCAT(DISTINCT modification_location.id ORDER BY vehicle_modification_location.position ASC SEPARATOR '+') AS modificationLocationIds,
                   GROUP_CONCAT(DISTINCT modification_location_locale.name ORDER BY vehicle_modification_location.position ASC SEPARATOR '+') AS modificationLocationNames,
                   vehicle.optimized, vehicle_locale.comment, vehicle_locale.unlock_vehicle AS unlockVehicle, vehicle.evolution
            FROM vehicle
            LEFT JOIN vehicle_locale ON vehicle_locale.vehicle_id = vehicle.id AND vehicle_locale.locale_id = :localeId
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN property ON property.id = vehicle_user.property_id
            LEFT JOIN property wanted_property ON wanted_property.id = vehicle_user.wanted_property_id
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN vehicle_vehicle ON vehicle_vehicle.vehicle_two_id = vehicle.id
            LEFT JOIN vehicle vehicle_one ON vehicle_one.id = vehicle_vehicle.vehicle_one_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale
                ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            WHERE vehicle.id = :vehicleId
            GROUP BY vehicle.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('vehicleId', $vehicleId);

        return $this->configurationHelper->removeExternalData([$statement->executeQuery()->fetchAssociative()])[0];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicles(array $filter, string $count = '', string $sum = ''): array|float
    {
        $perPage   = $this->parameterBag->get('per_page');
        $values    = $filter['search'] ? explode(' ', $filter['search']) : [];
        $hasValues = false;

        if ($count !== '') {
            if (in_array($count, ['owned', 'property_id', 'wanted_property_id'])) {
                $query = "SELECT COUNT(DISTINCT vehicle_user." . $count . ") AS count";
            } else {
                $query = "SELECT COUNT(DISTINCT vehicle." . $count . ") AS count";
            }
        } elseif ($sum === 'summary') {
            $query = "
                SELECT COALESCE(SUM(g.groupOwned = 1 OR g.notAcquirable = 0), 0) AS total, COALESCE(SUM(g.groupOwned), 0) AS owned,
                       COALESCE(SUM(g.ownedPrice), 0) AS ownedPrice, COALESCE(SUM(g.ownedStored), 0) AS ownedStored,
                       COALESCE(SUM(g.ownedNotStored), 0) AS ownedNotStored, COALESCE(SUM(g.ownedNotStoredWithoutPlace), 0) AS ownedNotStoredWithoutPlace,
                       COALESCE(SUM(g.ownedNotBuyable), 0) AS ownedNotBuyable, COALESCE(SUM(g.buyable), 0) AS buyable,
                       COALESCE(SUM(IF(g.buyable = 1, g.price, 0)), 0) AS buyablePrice,
                       COALESCE(SUM(g.buyable = 1 AND g.wanted = 1), 0) AS toBuy,
                       COALESCE(SUM(IF(g.buyable = 1 AND g.wanted = 1, g.price, 0)), 0) AS toBuyPrice,
                       COALESCE(SUM(g.buyable = 1 AND g.wanted = 1 AND g.wantedProperty = 0), 0) AS toBuyWithoutProperty,
                       COALESCE(SUM(g.groupOwned = 0 AND g.notAcquirable = 0 AND g.buyable = 0), 0) AS unavailable,
                       COALESCE(SUM(g.buyable = 1 AND g.needsPlace = 0), 0) AS withoutPlace,
                       COALESCE(SUM(g.buyable = 1 AND g.needsPlace = 1), 0) AS needPlace,
                       COALESCE(SUM(g.discounted), 0) AS discounted,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discountedBuyable, 0)), 0) AS discountedNotOwned,
                       COALESCE(SUM(IF(g.groupOwned = 0 AND g.wanted = 1, g.discountedBuyable, 0)), 0) AS toBuyDiscounted,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discountedPrice, 0)), 0) AS discountedPrice,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discountedBasePrice, 0)), 0) AS discountedBasePrice,
                       COALESCE(SUM(IF(g.groupOwned = 0 AND g.wanted = 1, g.discountedPrice, 0)), 0) AS toBuyDiscountedPrice,
                       COALESCE(SUM(IF(g.groupOwned = 0 AND g.wanted = 1, g.discountedBasePrice, 0)), 0) AS toBuyDiscountedBasePrice
                FROM (
                    SELECT h.*, (h.groupOwned = 0 AND h.anyBuyable = 1) AS buyable
                    FROM (
                        SELECT s.groupId,
                               EXISTS (
                                   SELECT 1
                                   FROM vehicle_user group_user
                                   LEFT JOIN vehicle group_vehicle ON group_vehicle.id = group_user.vehicle_id
                                   LEFT JOIN vehicle_vehicle group_vehicle_vehicle ON group_vehicle_vehicle.vehicle_two_id = group_user.vehicle_id
                                   WHERE group_user.user_id = :userId AND group_user.owned = 1
                                   AND (
                                       group_user.vehicle_id = s.groupId
                                       OR (group_vehicle.evolution = 1 AND group_vehicle_vehicle.vehicle_one_id = s.groupId)
                                   )
                               ) AS groupOwned,
                               MIN(s.not_acquirable) AS notAcquirable, MAX(s.buyable_now) AS anyBuyable,
                               MAX(s.wanted_property_id IS NOT NULL OR s.needs_place = 0 OR s.without_property = 1) AS wanted,
                               MAX(s.wanted_property_id IS NOT NULL) AS wantedProperty, SUM(IF(s.owned = 1, s.price, 0)) AS ownedPrice,
                               SUM(s.owned = 1 AND s.property_id IS NOT NULL) AS ownedStored, SUM(s.owned = 1 AND s.property_id IS NULL) AS ownedNotStored,
                               SUM(s.owned = 1 AND s.property_id IS NULL AND s.needs_place = 0) AS ownedNotStoredWithoutPlace,
                               SUM(s.owned = 1 AND s.buyable_now = 0) AS ownedNotBuyable,
                               MIN(IF(s.buyable_now = 1, s.promo_price, NULL)) AS price, MIN(IF(s.buyable_now = 1, s.needs_place, NULL)) AS needsPlace,
                               SUM(s.discounted_percentage IS NOT NULL) AS discounted,
                               SUM(s.buyable_now = 1 AND s.discounted_percentage IS NOT NULL) AS discountedBuyable,
                               SUM(IF(s.buyable_now = 1 AND s.discounted_percentage IS NOT NULL, s.promo_price, 0)) AS discountedPrice,
                               SUM(IF(s.buyable_now = 1 AND s.discounted_percentage IS NOT NULL, s.price, 0)) AS discountedBasePrice
                        FROM (
                            SELECT DISTINCT vehicle.id, vehicle_user.owned, vehicle_user.property_id, vehicle_user.wanted_property_id, vehicle.price,
                                            vehicle.discounted_percentage, vehicle.price * (100 - COALESCE(vehicle.discounted_percentage, 0)) / 100 AS promo_price,
                                            COALESCE(
                                                (
                                                    SELECT vehicle_vehicle_group.vehicle_one_id
                                                    FROM vehicle_vehicle vehicle_vehicle_group
                                                    WHERE vehicle_vehicle_group.vehicle_two_id = vehicle.id AND vehicle.evolution = 1
                                                    ORDER BY vehicle_vehicle_group.position
                                                    LIMIT 1
                                                ),
                                                vehicle.id
                                            ) AS groupId,
                                            EXISTS (
                                                SELECT 1
                                                FROM vehicle_vehicle_acquisition not_acquirable
                                                INNER JOIN vehicle_acquisition not_acquirable_acquisition
                                                    ON not_acquirable_acquisition.id = not_acquirable.vehicle_acquisition_id
                                                    AND not_acquirable_acquisition.acquirable = 0
                                                WHERE not_acquirable.vehicle_id = vehicle.id
                                            ) AS not_acquirable,
                                            (
                                                NOT EXISTS (
                                                    SELECT 1
                                                    FROM vehicle_vehicle_acquisition not_acquirable
                                                    INNER JOIN vehicle_acquisition not_acquirable_acquisition
                                                        ON not_acquirable_acquisition.id = not_acquirable.vehicle_acquisition_id
                                                        AND not_acquirable_acquisition.acquirable = 0
                                                    WHERE not_acquirable.vehicle_id = vehicle.id
                                                )
                                                AND (
                                                    vehicle.discounted_percentage IS NOT NULL
                                                    OR NOT EXISTS (
                                                        SELECT 1
                                                        FROM vehicle_vehicle_acquisition unavailable
                                                        INNER JOIN vehicle_acquisition unavailable_acquisition
                                                            ON unavailable_acquisition.id = unavailable.vehicle_acquisition_id AND unavailable_acquisition.available = 0
                                                        WHERE unavailable.vehicle_id = vehicle.id
                                                    )
                                                )
                                            ) AS buyable_now,
                                            NOT EXISTS (
                                                SELECT 1
                                                FROM vehicle_storage_location without_place
                                                INNER JOIN storage_location without_place_location
                                                    ON without_place_location.id = without_place.storage_location_id AND without_place_location.property_required = 0
                                                WHERE without_place.vehicle_id = vehicle.id
                                            ) AS needs_place,
                                            NOT EXISTS (
                                                SELECT 1
                                                FROM vehicle_storage_location with_place
                                                INNER JOIN storage_location with_place_location
                                                    ON with_place_location.id = with_place.storage_location_id AND with_place_location.property_required = 1
                                                WHERE with_place.vehicle_id = vehicle.id
                                            ) AS without_property
            ";
        } elseif ($sum !== '') {
            $query = "SELECT SUM(s." . $sum . ") AS sum FROM ( SELECT DISTINCT vehicle.id, vehicle_user.owned, vehicle.price";
        } else {
            $query = "
                SELECT vehicle.id, CONCAT('uploads/vehicle/', vehicle.path) AS webPath, vehicle.url, vehicle.real_life_vehicle AS realLifeVehicle, vehicle.name,
                GROUP_CONCAT(DISTINCT NULLIF(CONCAT_WS(' ', vehicle_one_manufacturer.name, vehicle_one.name), '') ORDER BY vehicle_vehicle.position ASC SEPARATOR '+') AS vehicleOneNames,
                vehicle.evolution, vehicle.optimized, vehicle_manufacturer.name AS vehicleManufacturerName, title_update.name AS titleUpdateName,
                vehicle_user.owned, property.id AS propertyId, property.id AS propertyId, property.name AS propertyName,
                wanted_property.id AS wantedPropertyId, wanted_property.name AS wantedPropertyName, vehicle.seats,
                GROUP_CONCAT(DISTINCT vehicle_class.id ORDER BY vehicle_vehicle_class.position ASC SEPARATOR '+') AS vehicleClassIds,
                GROUP_CONCAT(DISTINCT vehicle_class_locale.name ORDER BY vehicle_vehicle_class.position ASC SEPARATOR '+') AS vehicleClassNames,
                vehicle_drivetrain_locale.name AS vehicleDrivetrainName, vehicle.price, vehicle.discounted_price AS discountedPrice,
                vehicle.discounted_percentage AS discountedPercentage, vehicle_locale.comment, vehicle_locale.unlock_vehicle AS unlockVehicle, vehicle.weight,
                vehicle.gears, vehicle.top_speed_kilometers AS topSpeedKilometers, vehicle.top_speed_kilometers_maximum AS topSpeedKilometersMaximum,
                vehicle.top_speed_miles AS topSpeedMiles, vehicle.top_speed_miles_maximum AS topSpeedMilesMaximum,
                vehicle.speed, vehicle.acceleration, vehicle.braking, vehicle.handling,
                GROUP_CONCAT(DISTINCT vehicle_acquisition_locale.name ORDER BY vehicle_vehicle_acquisition.position ASC SEPARATOR '+') AS vehicleAcquisitionNames,
                GROUP_CONCAT(DISTINCT storage_location_locale.name ORDER BY vehicle_storage_location.position ASC SEPARATOR '+') AS storageLocationNames,
                GROUP_CONCAT(DISTINCT delivery_method_locale.name ORDER BY vehicle_delivery_method.position ASC SEPARATOR '+') AS deliveryMethodNames,
                GROUP_CONCAT(DISTINCT modification_location_locale.name ORDER BY vehicle_modification_location.position ASC SEPARATOR '+') AS modificationLocationNames,
                GROUP_CONCAT(
                    DISTINCT property_type_storage_location.property_type_id
                    ORDER BY property_type_storage_location.property_type_id ASC
                    SEPARATOR '+'
                ) AS authorizedPropertyTypeIds
            ";
        }

        // @note: NO_SEMIJOIN garde la sous-requête de recherche à part ; fusionnée, ses jointures s'ajoutent à celles de la requête
        // et l'optimiseur MySQL met plusieurs secondes à choisir leur ordre
        $query .= "
            FROM vehicle
            LEFT JOIN vehicle_locale ON vehicle_locale.vehicle_id = vehicle.id AND vehicle_locale.locale_id = :localeId
            LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
            LEFT JOIN property ON property.id = vehicle_user.property_id
            LEFT JOIN vehicle_vehicle ON vehicle_vehicle.vehicle_two_id = vehicle.id
            LEFT JOIN vehicle vehicle_one ON vehicle_one.id = vehicle_vehicle.vehicle_one_id
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN vehicle_manufacturer vehicle_one_manufacturer ON vehicle_one_manufacturer.id = vehicle_one.vehicle_manufacturer_id
            LEFT JOIN title_update ON title_update.id = vehicle.title_update_id
            LEFT JOIN property wanted_property ON wanted_property.id = vehicle_user.wanted_property_id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_drivetrain ON vehicle_drivetrain.id = vehicle.vehicle_drivetrain_id
            LEFT JOIN vehicle_drivetrain_locale
                ON vehicle_drivetrain_locale.vehicle_drivetrain_id = vehicle_drivetrain.id AND vehicle_drivetrain_locale.locale_id = :localeId
            LEFT JOIN vehicle_vehicle_acquisition ON vehicle_vehicle_acquisition.vehicle_id = vehicle.id
            LEFT JOIN vehicle_acquisition ON vehicle_acquisition.id = vehicle_vehicle_acquisition.vehicle_acquisition_id
            LEFT JOIN vehicle_acquisition_locale
                ON vehicle_acquisition_locale.vehicle_acquisition_id = vehicle_acquisition.id AND vehicle_acquisition_locale.locale_id = :localeId
            LEFT JOIN vehicle_storage_location ON vehicle_storage_location.vehicle_id = vehicle.id
            LEFT JOIN storage_location ON storage_location.id = vehicle_storage_location.storage_location_id
            LEFT JOIN storage_location_locale
                ON storage_location_locale.storage_location_id = storage_location.id AND storage_location_locale.locale_id = :localeId
            LEFT JOIN vehicle_delivery_method ON vehicle_delivery_method.vehicle_id = vehicle.id
            LEFT JOIN delivery_method ON delivery_method.id = vehicle_delivery_method.delivery_method_id
            LEFT JOIN delivery_method_locale
                ON delivery_method_locale.delivery_method_id = delivery_method.id AND delivery_method_locale.locale_id = :localeId
            LEFT JOIN vehicle_modification_location ON vehicle_modification_location.vehicle_id = vehicle.id
            LEFT JOIN modification_location ON modification_location.id = vehicle_modification_location.modification_location_id
            LEFT JOIN modification_location_locale
                ON modification_location_locale.modification_location_id = modification_location.id AND modification_location_locale.locale_id = :localeId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.storage_location_id = storage_location.id
            WHERE vehicle.id IN (
                SELECT /*+ NO_SEMIJOIN() */ DISTINCT vehicle_search.id
                FROM vehicle AS vehicle_search
                LEFT JOIN vehicle_vehicle_class vehicle_vehicle_class_search ON vehicle_vehicle_class_search.vehicle_id = vehicle_search.id
                LEFT JOIN vehicle_class vehicle_class_search ON vehicle_class_search.id = vehicle_vehicle_class_search.vehicle_class_id
                LEFT JOIN vehicle_class_locale vehicle_class_search_locale
                    ON vehicle_class_search_locale.vehicle_class_id = vehicle_class_search.id AND vehicle_class_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_vehicle_acquisition vehicle_vehicle_acquisition_search ON vehicle_vehicle_acquisition_search.vehicle_id = vehicle_search.id
                LEFT JOIN vehicle_acquisition vehicle_acquisition_search
                    ON vehicle_acquisition_search.id = vehicle_vehicle_acquisition_search.vehicle_acquisition_id
                LEFT JOIN vehicle_acquisition_locale vehicle_acquisition_search_locale
                    ON vehicle_acquisition_search_locale.vehicle_acquisition_id = vehicle_acquisition_search.id
                    AND vehicle_acquisition_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_storage_location vehicle_storage_location_search ON vehicle_storage_location_search.vehicle_id = vehicle_search.id
                LEFT JOIN storage_location storage_location_search ON storage_location_search.id = vehicle_storage_location_search.storage_location_id
                LEFT JOIN storage_location_locale storage_location_search_locale
                    ON storage_location_search_locale.storage_location_id = storage_location_search.id AND storage_location_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_delivery_method vehicle_delivery_method_search ON vehicle_delivery_method_search.vehicle_id = vehicle_search.id
                LEFT JOIN delivery_method delivery_method_search ON delivery_method_search.id = vehicle_delivery_method_search.delivery_method_id
                LEFT JOIN delivery_method_locale delivery_method_search_locale
                    ON delivery_method_search_locale.delivery_method_id = delivery_method_search.id AND delivery_method_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_modification_location vehicle_modification_location_search
                    ON vehicle_modification_location_search.vehicle_id = vehicle_search.id
                LEFT JOIN modification_location modification_location_search
                    ON modification_location_search.id = vehicle_modification_location_search.modification_location_id
                LEFT JOIN modification_location_locale modification_location_search_locale
                    ON modification_location_search_locale.modification_location_id = modification_location_search.id
                    AND modification_location_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_locale vehicle_search_locale
                    ON vehicle_search_locale.vehicle_id = vehicle_search.id AND vehicle_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_manufacturer vehicle_manufacturer_search ON vehicle_manufacturer_search.id = vehicle_search.vehicle_manufacturer_id
                LEFT JOIN title_update title_update_search ON title_update_search.id = vehicle_search.title_update_id
                LEFT JOIN vehicle_drivetrain_locale vehicle_drivetrain_search_locale
                    ON vehicle_drivetrain_search_locale.vehicle_drivetrain_id = vehicle_search.vehicle_drivetrain_id
                    AND vehicle_drivetrain_search_locale.locale_id = :localeId
        ";

        if ($values !== []) {
            foreach ($values as $key => $value) {
                if ($key) {
                    $query .= "
                        AND (
                            vehicle_search.name LIKE :search_" . $key . "
                            OR vehicle_search.real_life_vehicle LIKE :search_" . $key . "
                            OR vehicle_manufacturer_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR vehicle_class_search_locale.name LIKE :search_" . $key . "
                            OR vehicle_drivetrain_search_locale.name LIKE :search_" . $key . "
                            OR vehicle_search.price LIKE :search_" . $key . "
                            OR vehicle_search.discounted_price LIKE :search_" . $key . "
                            OR vehicle_search.discounted_percentage LIKE :search_" . $key . "
                            OR vehicle_search_locale.comment LIKE :search_" . $key . "
                            OR vehicle_search_locale.unlock_vehicle LIKE :search_" . $key . "
                            OR vehicle_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR storage_location_search_locale.name LIKE :search_" . $key . "
                            OR delivery_method_search_locale.name LIKE :search_" . $key . "
                            OR modification_location_search_locale.name LIKE :search_" . $key . "
                        )
                    ";
                } else {
                    $query .= "
                        WHERE (
                            vehicle_search.name LIKE :search_" . $key . "
                            OR vehicle_search.real_life_vehicle LIKE :search_" . $key . "
                            OR vehicle_manufacturer_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR vehicle_class_search_locale.name LIKE :search_" . $key . "
                            OR vehicle_drivetrain_search_locale.name LIKE :search_" . $key . "
                            OR vehicle_search.price LIKE :search_" . $key . "
                            OR vehicle_search.discounted_price LIKE :search_" . $key . "
                            OR vehicle_search.discounted_percentage LIKE :search_" . $key . "
                            OR vehicle_search_locale.comment LIKE :search_" . $key . "
                            OR vehicle_search_locale.unlock_vehicle LIKE :search_" . $key . "
                            OR vehicle_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR storage_location_search_locale.name LIKE :search_" . $key . "
                            OR delivery_method_search_locale.name LIKE :search_" . $key . "
                            OR modification_location_search_locale.name LIKE :search_" . $key . "
                        )
                    ";
                }
            }

            $hasValues = true;
        }

        if (!empty($filter['vehicleClassIds'])) {
            foreach ($filter['vehicleClassIds'] as $key => $vehicleClassId) {
                if ($key) {
                    $query .= " OR vehicle_class_search.id = :vehicleClassId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_class_search.id = :vehicleClassId_" . $key;
                    } else {
                        $query .= " WHERE (vehicle_class_search.id = :vehicleClassId_" . $key;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if (!empty($filter['vehicleAcquisitionIds'])) {
            foreach ($filter['vehicleAcquisitionIds'] as $key => $vehicleAcquisitionId) {
                if ($key) {
                    $query .= " OR vehicle_vehicle_acquisition_search.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_vehicle_acquisition_search.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                    } else {
                        $query .= " WHERE (vehicle_vehicle_acquisition_search.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if (!empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                if ($key) {
                    $query .= " OR vehicle_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                    } else {
                        $query .= " WHERE (vehicle_storage_location_search.storage_location_id = :storageLocationId_" . $key;
                    }
                }
            }

            $query .= ")";
        }

        $query .= ")";

        if ($filter['vehicleManufacturerId'] !== 0) {
            $query .= " AND vehicle_manufacturer.id = :vehicleManufacturerId";
        }

        if ($filter['titleUpdateId'] !== 0) {
            $query .= " AND title_update.id = :titleUpdateId";
        }

        if ($sum !== '' && $sum !== 'summary') {
            $query .= " AND vehicle_user.owned = 1";
        } elseif ($filter['owned'] !== null) {
            $query .= " AND vehicle_user.owned = :owned";
        }

        if ($filter['propertyId'] !== 0) {
            $query .= " AND vehicle_user.property_id = :propertyId";
        }

        if ($filter['wantedPropertyId'] !== 0) {
            $query .= " AND vehicle_user.wanted_property_id = :wantedPropertyId";
        }

        if ($filter['seats'] !== 0) {
            $query .= " AND vehicle.seats = :seats";
        }

        if ($filter['vehicleTwo'] !== 0) {
            switch ($filter['vehicleTwo']) {
                case Vehicle::FILTER_VEHICLE_TWO_YES:
                    $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                    break;
                case Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS:
                    $query .= " AND (vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) OR vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle))";
                    break;
                case Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS:
                    $query .= " AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                    break;
                case Vehicle::FILTER_VEHICLE_TWO_NO:
                    $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                    break;
            }
        }

        if ($filter['optimized'] !== null) {
            $query .= " AND vehicle.optimized = :optimized";
        }

        if ($filter['evolution'] !== 0) {
            switch ($filter['evolution']) {
                case Vehicle::FILTER_EVOLUTION_YES:
                    $query .= " AND vehicle.evolution = 1";
                    break;
                case Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS:
                    $query .= " AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                    break;
                case Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS:
                    $query .= " AND vehicle.evolution = 0";
                    break;
                case Vehicle::FILTER_EVOLUTION_NO:
                    $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                    break;
            }
        }

        if ($filter['wantedProperty'] !== null) {
            if ($filter['wantedProperty'] !== false) {
                $query .= " AND vehicle_user.wanted_property_id IS NOT NULL";
            } else {
                $query .= " AND vehicle_user.wanted_property_id IS NULL";
            }
        }

        if (!empty($filter['notVehicleClassIds'])) {
            foreach ($filter['notVehicleClassIds'] as $key => $notVehicleClassId) {
                $query .= "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM vehicle_vehicle_class vehicle_vehicle_class_not
                        WHERE vehicle_vehicle_class_not.vehicle_id = vehicle.id
                        AND vehicle_vehicle_class_not.vehicle_class_id = :notVehicleClassId_" . $key . "
                    )
                ";
            }
        }

        if (!empty($filter['notVehicleAcquisitionIds'])) {
            foreach ($filter['notVehicleAcquisitionIds'] as $key => $notVehicleAcquisitionId) {
                $query .= "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM vehicle_vehicle_acquisition vehicle_vehicle_acquisition_not
                        WHERE vehicle_vehicle_acquisition_not.vehicle_id = vehicle.id
                        AND vehicle_vehicle_acquisition_not.vehicle_acquisition_id = :notVehicleAcquisitionId_" . $key . "
                    )
                ";
            }
        }

        if (!empty($filter['notStorageLocationIds'])) {
            foreach ($filter['notStorageLocationIds'] as $key => $notStorageLocationId) {
                $query .= "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM vehicle_storage_location vehicle_storage_location_not
                        WHERE vehicle_storage_location_not.vehicle_id = vehicle.id
                        AND vehicle_storage_location_not.storage_location_id = :notStorageLocationId_" . $key . "
                    )
                ";
            }
        }

        $query .= $count !== '' || $sum !== '' ? "" : " GROUP BY vehicle.id";

        if ($count === '' && $sum === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy(
                $query, $filter['order'], [
                    'vehicle-average' => '(
                        (
                            COALESCE(vehicle.speed, 0) +
                            COALESCE(vehicle.acceleration, 0) +
                            COALESCE(vehicle.braking, 0) +
                            COALESCE(vehicle.handling, 0)
                        ) * 1.0
                    ) / (
                        (IF(vehicle.speed IS NOT NULL, 1, 0)) +
                        (IF(vehicle.acceleration IS NOT NULL, 1, 0)) +
                        (IF(vehicle.braking IS NOT NULL, 1, 0)) +
                        (IF(vehicle.handling IS NOT NULL, 1, 0))
                    )'
                ]
            );
        }

        $query .= $count !== '' || $sum !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        if ($sum === 'summary') {
            $query .= ') s GROUP BY s.groupId) h) g';
        } elseif ($sum !== '') {
            $query .= ') s';
        }

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($filter['vehicleManufacturerId'] !== 0) {
            $statement->bindValue('vehicleManufacturerId', $filter['vehicleManufacturerId']);
        }

        if ($filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if (($sum === '' || $sum === 'summary') && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if ($filter['propertyId'] !== 0) {
            $statement->bindValue('propertyId', $filter['propertyId']);
        }

        if ($filter['wantedPropertyId'] !== 0) {
            $statement->bindValue('wantedPropertyId', $filter['wantedPropertyId']);
        }

        if ($filter['seats'] !== 0) {
            $statement->bindValue('seats', $filter['seats']);
        }

        if (!empty($filter['vehicleClassIds'])) {
            foreach ($filter['vehicleClassIds'] as $key => $vehicleClassId) {
                $statement->bindValue('vehicleClassId_' . $key, $vehicleClassId);
            }
        }

        if (!empty($filter['vehicleAcquisitionIds'])) {
            foreach ($filter['vehicleAcquisitionIds'] as $key => $vehicleAcquisitionId) {
                $statement->bindValue('vehicleAcquisitionId_' . $key, $vehicleAcquisitionId);
            }
        }

        if (!empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                $statement->bindValue('storageLocationId_' . $key, $storageLocationId);
            }
        }

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($filter['optimized'] !== null) {
            $statement->bindValue('optimized', $filter['optimized']);
        }

        if (!empty($filter['notVehicleClassIds'])) {
            foreach ($filter['notVehicleClassIds'] as $key => $notVehicleClassId) {
                $statement->bindValue('notVehicleClassId_' . $key, $notVehicleClassId);
            }
        }

        if (!empty($filter['notVehicleAcquisitionIds'])) {
            foreach ($filter['notVehicleAcquisitionIds'] as $key => $notVehicleAcquisitionId) {
                $statement->bindValue('notVehicleAcquisitionId_' . $key, $notVehicleAcquisitionId);
            }
        }

        if (!empty($filter['notStorageLocationIds'])) {
            foreach ($filter['notStorageLocationIds'] as $key => $notStorageLocationId) {
                $statement->bindValue('notStorageLocationId_' . $key, $notStorageLocationId);
            }
        }

        if ($count !== '') {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        if ($sum === 'summary') {
            return $statement->executeQuery()->fetchAssociative();
        }

        if ($sum !== '') {
            return $statement->executeQuery()->fetchAssociative()['sum'] ?? 0;
        }

        return $this->configurationHelper->removeExternalData($statement->executeQuery()->fetchAllAssociative());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getVehicleTwoLabelVehicle(int $vehicleTwo): string
    {
        $filter = match ($vehicleTwo) {
            Vehicle::FILTER_VEHICLE_TWO_ALL             => 'all',
            Vehicle::FILTER_VEHICLE_TWO_YES             => 'yes',
            Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS => 'yes_with_others',
            Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS  => 'no_with_others',
            Vehicle::FILTER_VEHICLE_TWO_NO              => 'no',
            default                                     => null,
        };

        if ($filter === null) {
            return '';
        }

        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('filter_two', ['entity' => 'vehicles', 'filter' => $filter])
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWantedProperties(): array
    {
        $query = "
            SELECT property.id, property.name, COALESCE(property.capacity_vehicles, 0) AS capacityVehicles,
                   COALESCE(property.capacity_bicycles, 0) AS capacityBicycles, property.property_type_id AS propertyTypeId,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_two.id ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.wanted_property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            WHERE property_user.wanted = 1 AND property.id NOT IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property)
            GROUP BY property.id
            ORDER BY property.name
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if (!empty($properties = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($properties as $key => $property) {
                $properties[$key]['propertyTwos']   = [];
                $properties[$key]['propertyTwoIds'] = $properties[$key]['propertyTwoIds'] ? explode('+', $properties[$key]['propertyTwoIds']) : [];

                foreach ($properties[$key]['propertyTwoIds'] as $propertyTwoId) {
                    $properties[$key]['propertyTwos'][] = $this->propertyHelper->getWantedProperty($propertyTwoId);
                }

                unset($properties[$key]['propertyTwoIds']);
            }
        }

        return $properties;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function releaseLinkedEvolutions(int $vehicleId): void
    {
        $query = "
            UPDATE vehicle_user
            SET vehicle_user.owned = 0, vehicle_user.property_id = NULL, vehicle_user.updated_at = :updatedAt, vehicle_user.updated_by_id = :userId
            WHERE vehicle_user.user_id = :userId
            AND vehicle_user.vehicle_id IN (
                SELECT vehicle_vehicle.vehicle_one_id
                FROM vehicle_vehicle
                INNER JOIN vehicle evolution ON evolution.id = vehicle_vehicle.vehicle_two_id AND evolution.evolution = 1
                WHERE vehicle_vehicle.vehicle_two_id = :vehicleId
                UNION
                SELECT vehicle_vehicle.vehicle_two_id
                FROM vehicle_vehicle
                INNER JOIN vehicle evolution ON evolution.id = vehicle_vehicle.vehicle_two_id AND evolution.evolution = 1
                WHERE vehicle_vehicle.vehicle_one_id = :vehicleId
            )
        ";

        $this->entityManager->getConnection()->executeStatement(
            $query, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $this->security->getUser()->id, 'vehicleId' => $vehicleId]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    private function addValues($statement, array $filter, string $type): void
    {
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($type !== 'VehicleManufacturerId' && $filter['vehicleManufacturerId'] !== 0) {
            $statement->bindValue('vehicleManufacturerId', $filter['vehicleManufacturerId']);
        }

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($type !== 'Owned' && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if ($type !== 'PropertyId' && $filter['propertyId'] !== 0) {
            $statement->bindValue('propertyId', $filter['propertyId']);
        }

        if ($type !== 'WantedPropertyId' && $filter['wantedPropertyId'] !== 0) {
            $statement->bindValue('wantedPropertyId', $filter['wantedPropertyId']);
        }

        if ($type !== 'Seat' && $filter['seats'] !== 0) {
            $statement->bindValue('seats', $filter['seats']);
        }

        if ($type !== 'VehicleClassId' && !empty($filter['vehicleClassIds'])) {
            foreach ($filter['vehicleClassIds'] as $key => $vehicleClassId) {
                $statement->bindValue('vehicleClassId_' . $key, $vehicleClassId);
            }
        }

        if ($type !== 'VehicleAcquisitionId' && !empty($filter['vehicleAcquisitionIds'])) {
            foreach ($filter['vehicleAcquisitionIds'] as $key => $vehicleAcquisitionId) {
                $statement->bindValue('vehicleAcquisitionId_' . $key, $vehicleAcquisitionId);
            }
        }

        if ($type !== 'StorageLocationId' && !empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                $statement->bindValue('storageLocationId_' . $key, $storageLocationId);
            }
        }

        if ($type !== 'Optimized' && $filter['optimized'] !== null) {
            $statement->bindValue('optimized', $filter['optimized']);
        }

        if ($type !== 'NotVehicleClassId' && !empty($filter['notVehicleClassIds'])) {
            foreach ($filter['notVehicleClassIds'] as $key => $notVehicleClassId) {
                $statement->bindValue('notVehicleClassId_' . $key, $notVehicleClassId);
            }
        }

        if ($type !== 'NotVehicleAcquisitionId' && !empty($filter['notVehicleAcquisitionIds'])) {
            foreach ($filter['notVehicleAcquisitionIds'] as $key => $notVehicleAcquisitionId) {
                $statement->bindValue('notVehicleAcquisitionId_' . $key, $notVehicleAcquisitionId);
            }
        }

        if ($type !== 'NotStorageLocationId' && !empty($filter['notStorageLocationIds'])) {
            foreach ($filter['notStorageLocationIds'] as $key => $notStorageLocationId) {
                $statement->bindValue('notStorageLocationId_' . $key, $notStorageLocationId);
            }
        }
    }

    private function getQuery(array $filter, string $type, bool $verifyValues = false): string
    {
        $query     = '';
        $hasValues = !$verifyValues;

        if ($type !== 'VehicleManufacturerId' && $filter['vehicleManufacturerId'] !== 0) {
            if ($hasValues) {
                $query .= " AND vehicle_manufacturer.id = :vehicleManufacturerId";
            } else {
                $query     .= " WHERE vehicle_manufacturer.id = :vehicleManufacturerId";
                $hasValues = true;
            }
        }

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            if ($hasValues) {
                $query .= " AND title_update.id = :titleUpdateId";
            } else {
                $query     .= " WHERE title_update.id = :titleUpdateId";
                $hasValues = true;
            }
        }

        if ($type !== 'Owned' && $filter['owned'] !== null) {
            if ($hasValues) {
                $query .= " AND vehicle_user.owned = :owned";
            } else {
                $query     .= " WHERE vehicle_user.owned = :owned";
                $hasValues = true;
            }
        }

        if ($type !== 'PropertyId' && $filter['propertyId'] !== 0) {
            if ($hasValues) {
                $query .= " AND vehicle_user.property_id = :propertyId";
            } else {
                $query     .= " WHERE vehicle_user.property_id = :propertyId";
                $hasValues = true;
            }
        }

        if ($type !== 'WantedPropertyId' && $filter['wantedPropertyId'] !== 0) {
            if ($hasValues) {
                $query .= " AND vehicle_user.wanted_property_id = :wantedPropertyId";
            } else {
                $query     .= " WHERE vehicle_user.wanted_property_id = :wantedPropertyId";
                $hasValues = true;
            }
        }

        if ($type !== 'Seat' && $filter['seats'] !== 0) {
            if ($hasValues) {
                $query .= " AND vehicle.seats = :seats";
            } else {
                $query     .= " WHERE vehicle.seats = :seats";
                $hasValues = true;
            }
        }

        if ($type !== 'VehicleClassId' && !empty($filter['vehicleClassIds'])) {
            foreach ($filter['vehicleClassIds'] as $key => $vehicleClassId) {
                if ($key) {
                    $query .= " OR vehicle_class.id = :vehicleClassId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_class.id = :vehicleClassId_" . $key;
                    } else {
                        $query     .= " WHERE (vehicle_class.id = :vehicleClassId_" . $key;
                        $hasValues = true;
                    }
                }
            }

            $query .= ")";
        }

        if ($type !== 'VehicleAcquisitionId' && !empty($filter['vehicleAcquisitionIds'])) {
            foreach ($filter['vehicleAcquisitionIds'] as $key => $vehicleAcquisitionId) {
                if ($key) {
                    $query .= " OR vehicle_vehicle_acquisition.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_vehicle_acquisition.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                    } else {
                        $query     .= " WHERE (vehicle_vehicle_acquisition.vehicle_acquisition_id = :vehicleAcquisitionId_" . $key;
                        $hasValues = true;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if ($type !== 'StorageLocationId' && !empty($filter['storageLocationIds'])) {
            foreach ($filter['storageLocationIds'] as $key => $storageLocationId) {
                if ($key) {
                    $query .= " OR vehicle_storage_location.storage_location_id = :storageLocationId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (vehicle_storage_location.storage_location_id = :storageLocationId_" . $key;
                    } else {
                        $query     .= " WHERE (vehicle_storage_location.storage_location_id = :storageLocationId_" . $key;
                        $hasValues = true;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if ($type !== 'VehicleTwo' && $filter['vehicleTwo'] !== 0) {
            if ($hasValues) {
                switch ($filter['vehicleTwo']) {
                    case Vehicle::FILTER_VEHICLE_TWO_YES:
                        $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS:
                        $query .= " AND (vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) OR vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle))";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS:
                        $query .= " AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_NO:
                        $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                }
            } else {
                switch ($filter['vehicleTwo']) {
                    case Vehicle::FILTER_VEHICLE_TWO_YES:
                        $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_YES_WITH_OTHERS:
                        $query .= " WHERE (vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) OR vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle))";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_NO_WITH_OTHERS:
                        $query .= " WHERE vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                    case Vehicle::FILTER_VEHICLE_TWO_NO:
                        $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle) AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_two_id) FROM vehicle_vehicle)";
                        break;
                }
                $hasValues = true;
            }
        }

        if ($type !== 'Optimized' && $filter['optimized'] !== null) {
            if ($hasValues) {
                $query .= " AND vehicle.optimized = :optimized";
            } else {
                $query     .= " WHERE vehicle.optimized = :optimized";
                $hasValues = true;
            }
        }

        if ($type !== 'Evolution' && $filter['evolution'] !== 0) {
            if ($hasValues) {
                switch ($filter['evolution']) {
                    case Vehicle::FILTER_EVOLUTION_YES:
                        $query .= " AND vehicle.evolution = 1";
                        break;
                    case Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS:
                        $query .= " AND vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                        break;
                    case Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS:
                        $query .= " AND vehicle.evolution = 0";
                        break;
                    case Vehicle::FILTER_EVOLUTION_NO:
                        $query .= " AND vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                        break;
                }
            } else {
                switch ($filter['evolution']) {
                    case Vehicle::FILTER_EVOLUTION_YES:
                        $query .= " WHERE vehicle.evolution = 1";
                        break;
                    case Vehicle::FILTER_EVOLUTION_YES_WITH_OTHERS:
                        $query .= " WHERE vehicle.id NOT IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                        break;
                    case Vehicle::FILTER_EVOLUTION_NO_WITH_OTHERS:
                        $query .= " WHERE vehicle.evolution = 0";
                        break;
                    case Vehicle::FILTER_EVOLUTION_NO:
                        $query .= " WHERE vehicle.id IN (SELECT DISTINCT(vehicle_vehicle.vehicle_one_id) FROM vehicle_vehicle LEFT JOIN vehicle v ON vehicle_vehicle.vehicle_two_id = v.id WHERE v.evolution = 1)";
                        break;
                }
                $hasValues = true;
            }
        }

        if ($type !== 'WantedProperty' && $filter['wantedProperty'] !== null) {
            if ($filter['wantedProperty'] !== false) {
                if ($hasValues) {
                    $query .= " AND vehicle_user.wanted_property_id IS NOT NULL";
                } else {
                    $query     .= " WHERE vehicle_user.wanted_property_id IS NOT NULL";
                    $hasValues = true;
                }
            } else {
                if ($hasValues) {
                    $query .= " AND vehicle_user.wanted_property_id IS NULL";
                } else {
                    $query     .= " WHERE vehicle_user.wanted_property_id IS NULL";
                    $hasValues = true;
                }
            }
        }

        if ($type !== 'NotVehicleClassId' && !empty($filter['notVehicleClassIds'])) {
            foreach ($filter['notVehicleClassIds'] as $key => $notVehicleClassId) {
                if ($hasValues) {
                    $query .= " AND vehicle_class.id <> :notVehicleClassId_" . $key;
                } else {
                    $query     .= " WHERE vehicle_class.id <> :notVehicleClassId_" . $key;
                    $hasValues = true;
                }
            }
        }

        if ($type !== 'NotVehicleAcquisitionId' && !empty($filter['notVehicleAcquisitionIds'])) {
            foreach ($filter['notVehicleAcquisitionIds'] as $key => $notVehicleAcquisitionId) {
                if ($hasValues) {
                    $query .= " AND vehicle_vehicle_acquisition.vehicle_acquisition_id <> :notVehicleAcquisitionId_" . $key;
                } else {
                    $query     .= " WHERE vehicle_vehicle_acquisition.vehicle_acquisition_id <> :notVehicleAcquisitionId_" . $key;
                    $hasValues = true;
                }
            }
        }

        if ($type !== 'NotStorageLocationId' && !empty($filter['notStorageLocationIds'])) {
            foreach ($filter['notStorageLocationIds'] as $key => $notStorageLocationId) {
                if ($hasValues) {
                    $query .= " AND vehicle_storage_location.storage_location_id <> :notStorageLocationId_" . $key;
                } else {
                    $query     .= " WHERE vehicle_storage_location.storage_location_id <> :notStorageLocationId_" . $key;
                    $hasValues = true;
                }
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
        if ($filter['page'] > 1 && $this->getVehicles($filter, 'id') <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('vehicle', $filter);
        }

        return $filter;
    }
}

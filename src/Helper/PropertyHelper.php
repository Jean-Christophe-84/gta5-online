<?php

namespace App\Helper;

use App\Entity\Property;
use App\Entity\VehicleClass;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class PropertyHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack, private Security $security
    ) {
    }

    /**
     * @throws DoctrineDBALException
     */
    public function addPropertyTwos(array $properties): array
    {
        foreach ($properties as $key => $property) {
            $properties[$key]['propertyTwoIds'] = $property['propertyTwoIds'] ? explode('+', $property['propertyTwoIds']) : [];
        }

        $propertyTwos = $this->getPropertiesByIds(array_unique(array_merge([], ...array_column($properties, 'propertyTwoIds'))));

        foreach ($properties as $key => $property) {
            $properties[$key]['propertyTwos'] = array_values(array_intersect_key($propertyTwos, array_flip($property['propertyTwoIds'])));

            usort(
                $properties[$key]['propertyTwos'],
                fn(array $firstPropertyTwo, array $secondPropertyTwo): int => array_search($firstPropertyTwo['id'], $property['propertyTwoIds']) <=> array_search($secondPropertyTwo['id'], $property['propertyTwoIds'])
            );

            unset($properties[$key]['propertyTwoIds']);
        }

        return $properties;
    }

    /**
     * Supprime l'élément avec ses données propres : à n'appeler que si getDeletion() ne renvoie aucun blocage
     *
     * @throws DoctrineDBALException
     */
    public function delete(int $propertyId): void
    {
        $path = $this->entityManager->getConnection()->fetchOne("SELECT path FROM property WHERE id = :id", ['id' => $propertyId]);

        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM property_locale WHERE property_id = :id",
                "DELETE FROM property_property WHERE property_one_id = :id",
                "DELETE FROM property_property_acquisition WHERE property_id = :id",
                "DELETE FROM property_user WHERE property_id = :id",
                "UPDATE vehicle_user SET property_id = NULL WHERE property_id = :id",
                "UPDATE vehicle_user SET wanted_property_id = NULL WHERE wanted_property_id = :id",
                "DELETE FROM property WHERE id = :id"
            ],
            $propertyId
        );

        $this->configurationHelper->removeUpload('property', $path ? : null);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusinessMaximumMessage(?int $propertyId, ?int $businessId, bool $owned, bool $wanted): ?string
    {
        if (!$businessId || (!$owned && !$wanted)) {
            return null;
        }

        if (!$business = $this->getBusinessMaximums($propertyId)[$businessId] ?? null) {
            return null;
        }

        if ($owned && $business['ownedMessage']) {
            return $business['ownedMessage'];
        }

        return $wanted ? $business['wantedMessage'] : null;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusinessMaximums(?int $propertyId = null): array
    {
        $query = "
            SELECT business.id, business.property_type_id AS propertyTypeId, business.maximum_number AS maximumNumber, business_locale.name AS businessName,
                   COUNT(DISTINCT IF(property_user.owned = 1, property.id, NULL)) AS owned,
                   COUNT(DISTINCT IF(property_user.wanted = 1, property.id, NULL)) AS wanted
            FROM business
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN property ON property.business_id = business.id AND property.id <> :propertyId
                AND property.id NOT IN (SELECT property_property.property_two_id FROM property_property)
            LEFT JOIN property_user ON property_user.property_id = property.id AND property_user.user_id = :userId
            GROUP BY business.id
            ORDER BY business_locale.name
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('propertyId', $propertyId ?? 0);
        $statement->bindValue('userId', $this->security->getUser()->id);

        $isPropertyTwo = $propertyId && $this->entityManager->getConnection()->fetchOne(
            "SELECT 1 FROM property_property WHERE property_two_id = :propertyId LIMIT 1", ['propertyId' => $propertyId]
        );

        $businesses = [];

        foreach ($statement->executeQuery()->fetchAllAssociative() as $business) {
            foreach (['owned', 'wanted'] as $type) {
                $business[$type . 'Message'] = !$isPropertyTwo && $business[$type] >= $business['maximumNumber'] ? $this->configurationHelper->mbUcfirst(
                    $this->configurationHelper->getTranslateLocale(
                        'you_can_not_select_this_property_maximum_reached',
                        ['business' => $business['businessName'], 'maximum' => $business['maximumNumber'], 'type' => $type]
                    )
                ) : null;
            }

            $businesses[$business['id']] = $business;
        }

        return $businesses;
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $propertyId): array
    {
        $warnings = [];

        foreach ([
            'deletion_removed_from_users' => "SELECT COUNT(*) FROM property_user WHERE property_id = :id AND (owned = 1 OR wanted = 1)",
            'deletion_vehicles_taken_out' => "SELECT COUNT(*) FROM vehicle_user WHERE property_id = :id OR wanted_property_id = :id"
        ] as $key => $query) {
            if ($count = (int)$this->entityManager->getConnection()->fetchOne($query, ['id' => $propertyId])) {
                $warnings[] = $this->configurationHelper->getTranslateLocale($key, ['count' => $count]);
            }
        }

        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'parent_properties' => "SELECT property.name FROM property_property INNER JOIN property ON property.id = property_property.property_one_id WHERE property_property.property_two_id = :id ORDER BY property.name"
                ],
                $propertyId
            ),
            'warnings' => $warnings
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        $default =
            [
                'owned'                  => null,
                'wanted'                 => null,
                'propertyTypeId'         => 0,
                'businessId'             => 0,
                'propertyAreaId'         => 0,
                'propertyLocationId'     => 0,
                'titleUpdateId'          => 0,
                'capacityVehicles'       => null,
                'capacityBicycles'       => null,
                'propertyAcquisitionIds' => [],
                'search'                 => '',
                'page'                   => 1,
                'order'                  => ['property-name' => 'ASC']
            ];
        $filter  = $this->requestStack->getSession()->get('property');

        return $defaultFilter || !$filter || array_diff_key($default, $filter) ? $default : $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterBusinessIds(array $filter): array|int
    {
        $query = "
            SELECT business_locale.name, business.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE business.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'BusinessId') . " GROUP BY business.id ORDER BY business_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'BusinessId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterCapacityBicycles(array $filter): array|int
    {
        $query = "
            SELECT property.capacity_bicycles AS capacityBicycles, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property.capacity_bicycles IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'CapacityBicycles') . " GROUP BY property.capacity_bicycles ORDER BY property.capacity_bicycles";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'CapacityBicycles');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterCapacityVehicles(array $filter): array|int
    {
        $query = "
            SELECT property.capacity_vehicles AS capacityVehicles, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property.capacity_vehicles IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'CapacityVehicles') . " GROUP BY property.capacity_vehicles ORDER BY property.capacity_vehicles";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'CapacityVehicles');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterOwneds(array $filter): array
    {
        $query = "
            SELECT property_user.owned, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'Owned', true) . " GROUP BY property_user.owned ORDER BY property_user.owned";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'Owned');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyAcquisitionIds(array $filter): array|int
    {
        $query = "
            SELECT property_acquisition_locale.name, property_acquisition.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property_acquisition.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'PropertyAcquisitionId') . " GROUP BY property_acquisition.id ORDER BY property_acquisition_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'PropertyAcquisitionId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyAreaIds(array $filter): array|int
    {
        $query = "
            SELECT property_area.name, property_area.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property_area.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'PropertyAreaId') . " GROUP BY property_area.id ORDER BY property_area.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'PropertyAreaId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyLocationIds(array $filter): array|int
    {
        $query = "
            SELECT property_location.name, property_location.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property_location.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'PropertyLocationId') . " GROUP BY property_location.id ORDER BY property_location.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'PropertyLocationId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterPropertyTypeIds(array $filter): array|int
    {
        $query = "
            SELECT property_type_locale.name, property_type.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            WHERE property_type.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'PropertyTypeId') . " GROUP BY property_type.id ORDER BY property_type_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'PropertyTypeId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterTitleUpdateIds(array $filter): array|int
    {
        $query = "
            SELECT title_update.name, title_update.id, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
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
    public function getFilterWanteds(array $filter): array
    {
        $query = "
            SELECT property_user.wanted, COUNT(DISTINCT property.id) AS numberProperties
            FROM property
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'Wanted', true) . " GROUP BY property_user.wanted ORDER BY property_user.wanted";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'Wanted');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getParameters(array $filter): array
    {
        return [
            'filter'                       => $filter,
            'filterBusinessIds'            => $this->getFilterBusinessIds($filter),
            'filterCapacityBicycles'       => $this->getFilterCapacityBicycles($filter),
            'filterCapacityVehicles'       => $this->getFilterCapacityVehicles($filter),
            'filterOwneds'                 => $this->getFilterOwneds($filter),
            'filterPropertyAcquisitionIds' => $this->getFilterPropertyAcquisitionIds($filter),
            'filterPropertyAreaIds'        => $this->getFilterPropertyAreaIds($filter),
            'filterPropertyLocationIds'    => $this->getFilterPropertyLocationIds($filter),
            'filterPropertyTypeIds'        => $this->getFilterPropertyTypeIds($filter),
            'filterWanteds'                => $this->getFilterWanteds($filter),
            'filterTitleUpdateIds'         => $this->getFilterTitleUpdateIds($filter),
            'properties'                   => $this->getProperties($filter),
            'summary'                      => $this->security->isGranted(Property::PERMISSION_PROPERTY_OWN) ? $this->getSummary($filter) : null,
            'sumVehiclesPrices'            => $this->configurationHelper->formatNumber($this->getProperties($filter, false, 'vehicles_price')),
            'totalNames'                   => $this->getTotalNames()
        ];
    }

    /**
     * Une sous-propriété ne peut être obtenue que si l'une de ses propriétés parentes l'est
     *
     * @throws DoctrineDBALException
     */
    public function getParentPropertyMessage(?int $propertyId, bool $owned): ?string
    {
        if (!$propertyId || !$owned) {
            return null;
        }

        $parent = $this->entityManager->getConnection()->fetchAssociative(
            "
                SELECT GROUP_CONCAT(property.name ORDER BY property.name SEPARATOR ', ') AS names, MAX(COALESCE(property_user.owned, 0)) AS owned
                FROM property_property
                INNER JOIN property ON property.id = property_property.property_one_id
                LEFT JOIN property_user ON property_user.property_id = property.id AND property_user.user_id = :userId
                WHERE property_property.property_two_id = :propertyId
            ",
            ['propertyId' => $propertyId, 'userId' => $this->security->getUser()->id]
        );

        if (!$parent['names'] || $parent['owned']) {
            return null;
        }

        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('you_can_not_own_this_property_without_its_parent_property', ['names' => $parent['names']])
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getProperties(array $filter, bool $count = false, string $sum = ''): array|int
    {
        $perPage   = $this->parameterBag->get('per_page');
        $values    = $filter['search'] ? explode(' ', $filter['search']) : [];
        $hasValues = false;

        if ($count) {
            $query = "SELECT COUNT(DISTINCT property.id) AS count";
        } elseif ($sum === 'summary') {
            $query = "
                SELECT COALESCE(SUM(g.slots), 0) AS total, COALESCE(SUM(g.ownedSlots), 0) AS owned, COALESCE(SUM(g.ownedPrice), 0) AS ownedPrice,
                       COALESCE(SUM(IF(g.isFull = 0, g.toBuy, 0)), 0) AS toBuy, COALESCE(SUM(IF(g.isFull = 0, g.toBuyPrice, 0)), 0) AS toBuyPrice,
                       COALESCE(SUM(g.discounted), 0) AS discounted, COALESCE(SUM(IF(g.isFull = 0, g.discountedNotOwned, 0)), 0) AS discountedNotOwned,
                       COALESCE(SUM(IF(g.isFull = 0, g.toBuyDiscounted, 0)), 0) AS toBuyDiscounted,
                       COALESCE(SUM(IF(g.isFull = 0, g.discountedPrice, 0)), 0) AS discountedPrice,
                       COALESCE(SUM(IF(g.isFull = 0, g.discountedBasePrice, 0)), 0) AS discountedBasePrice,
                       COALESCE(SUM(IF(g.isFull = 0, g.toBuyDiscountedPrice, 0)), 0) AS toBuyDiscountedPrice,
                       COALESCE(SUM(IF(g.isFull = 0, g.toBuyDiscountedBasePrice, 0)), 0) AS toBuyDiscountedBasePrice,
                       COALESCE(SUM(g.ownedCapacityVehicles), 0) AS ownedCapacityVehicles,
                       COALESCE(SUM(g.ownedCapacityBicycles), 0) AS ownedCapacityBicycles,
                       COALESCE(SUM(g.wantedCapacityVehicles), 0) AS wantedCapacityVehicles,
                       COALESCE(SUM(g.wantedCapacityBicycles), 0) AS wantedCapacityBicycles,
                       COALESCE(SUM(g.storedVehicles), 0) AS storedVehicles, COALESCE(SUM(g.storedBicycles), 0) AS storedBicycles,
                       COALESCE(SUM(g.storedPrice), 0) AS storedPrice
                FROM (
                    SELECT h.*,
                           IF(
                               h.businessId IS NULL,
                               h.ownedSlots,
                               (
                                   SELECT COUNT(DISTINCT business_property.id)
                                   FROM property business_property
                                   INNER JOIN property_user business_property_user
                                       ON business_property_user.property_id = business_property.id AND business_property_user.user_id = :userId
                                       AND business_property_user.owned = 1
                                   WHERE business_property.business_id = h.businessId
                                   AND business_property.id NOT IN (SELECT business_property_property.property_two_id FROM property_property business_property_property)
                               )
                           ) >= h.maximumNumber AS isFull
                    FROM (
                        SELECT s.groupKey, MAX(s.business_id) AS businessId, MAX(s.maximum_number) AS maximumNumber,
                               LEAST(MAX(s.maximum_number), COUNT(s.id)) AS slots, LEAST(MAX(s.maximum_number), SUM(s.owned = 1)) AS ownedSlots,
                               SUM(IF(s.owned = 1, s.price, 0)) AS ownedPrice, SUM(s.owned = 0 AND s.wanted = 1) AS toBuy,
                               SUM(IF(s.owned = 0 AND s.wanted = 1, s.price * (100 - COALESCE(s.discounted_percentage, 0)) / 100, 0)) AS toBuyPrice,
                               SUM(s.discounted_percentage IS NOT NULL) AS discounted, SUM(s.owned = 0 AND s.discounted_percentage IS NOT NULL) AS discountedNotOwned,
                               SUM(s.owned = 0 AND s.wanted = 1 AND s.discounted_percentage IS NOT NULL) AS toBuyDiscounted,
                               SUM(IF(s.owned = 0 AND s.discounted_percentage IS NOT NULL, s.price * (100 - s.discounted_percentage) / 100, 0)) AS discountedPrice,
                               SUM(IF(s.owned = 0 AND s.discounted_percentage IS NOT NULL, s.price, 0)) AS discountedBasePrice,
                               SUM(
                                   IF(s.owned = 0 AND s.wanted = 1 AND s.discounted_percentage IS NOT NULL, s.price * (100 - s.discounted_percentage) / 100, 0)
                               ) AS toBuyDiscountedPrice,
                               SUM(IF(s.owned = 0 AND s.wanted = 1 AND s.discounted_percentage IS NOT NULL, s.price, 0)) AS toBuyDiscountedBasePrice,
                               SUM(s.owned_capacity_vehicles) AS ownedCapacityVehicles, SUM(s.owned_capacity_bicycles) AS ownedCapacityBicycles,
                               SUM(s.wanted_capacity_vehicles) AS wantedCapacityVehicles,
                               SUM(s.wanted_capacity_bicycles) AS wantedCapacityBicycles,
                               SUM(s.stored_vehicles) AS storedVehicles, SUM(s.stored_bicycles) AS storedBicycles, SUM(s.stored_price) AS storedPrice
                        FROM (
                            SELECT DISTINCT property.id, property_user.owned, property_user.wanted, property.price, property.discounted_percentage,
                                            property.business_id, COALESCE(business.maximum_number, 1) AS maximum_number,
                                            COALESCE(CONCAT('business_', property.business_id), CONCAT('property_', property.id)) AS groupKey,
                                            IF(
                                                property_user.owned = 1,
                                                COALESCE(property.capacity_vehicles, 0) + COALESCE(
                                                    (
                                                        SELECT SUM(sub_property.capacity_vehicles)
                                                        FROM property_property sub_property_property
                                                        INNER JOIN property sub_property ON sub_property.id = sub_property_property.property_two_id
                                                        INNER JOIN property_user sub_property_user
                                                            ON sub_property_user.property_id = sub_property.id AND sub_property_user.user_id = :userId
                                                            AND sub_property_user.owned = 1
                                                        WHERE sub_property_property.property_one_id = property.id
                                                    ),
                                                    0
                                                ),
                                                0
                                            ) AS owned_capacity_vehicles,
                                            IF(
                                                property_user.owned = 1,
                                                COALESCE(property.capacity_bicycles, 0) + COALESCE(
                                                    (
                                                        SELECT SUM(sub_property.capacity_bicycles)
                                                        FROM property_property sub_property_property
                                                        INNER JOIN property sub_property ON sub_property.id = sub_property_property.property_two_id
                                                        INNER JOIN property_user sub_property_user
                                                            ON sub_property_user.property_id = sub_property.id AND sub_property_user.user_id = :userId
                                                            AND sub_property_user.owned = 1
                                                        WHERE sub_property_property.property_one_id = property.id
                                                    ),
                                                    0
                                                ),
                                                0
                                            ) AS owned_capacity_bicycles,
                                            IF(
                                                property_user.wanted = 1,
                                                COALESCE(property.capacity_vehicles, 0) + COALESCE(
                                                    (
                                                        SELECT SUM(sub_property.capacity_vehicles)
                                                        FROM property_property sub_property_property
                                                        INNER JOIN property sub_property ON sub_property.id = sub_property_property.property_two_id
                                                        INNER JOIN property_user sub_property_user
                                                            ON sub_property_user.property_id = sub_property.id AND sub_property_user.user_id = :userId
                                                            AND sub_property_user.wanted = 1
                                                        WHERE sub_property_property.property_one_id = property.id
                                                    ),
                                                    0
                                                ),
                                                0
                                            ) AS wanted_capacity_vehicles,
                                            IF(
                                                property_user.wanted = 1,
                                                COALESCE(property.capacity_bicycles, 0) + COALESCE(
                                                    (
                                                        SELECT SUM(sub_property.capacity_bicycles)
                                                        FROM property_property sub_property_property
                                                        INNER JOIN property sub_property ON sub_property.id = sub_property_property.property_two_id
                                                        INNER JOIN property_user sub_property_user
                                                            ON sub_property_user.property_id = sub_property.id AND sub_property_user.user_id = :userId
                                                            AND sub_property_user.wanted = 1
                                                        WHERE sub_property_property.property_one_id = property.id
                                                    ),
                                                    0
                                                ),
                                                0
                                            ) AS wanted_capacity_bicycles,
                                            IF(
                                                property_user.owned = 1,
                                                (
                                                    SELECT COUNT(DISTINCT stored_vehicle_user.vehicle_id)
                                                    FROM vehicle_user stored_vehicle_user
                                                    WHERE stored_vehicle_user.user_id = :userId AND stored_vehicle_user.owned = 1
                                                    AND (
                                                        stored_vehicle_user.property_id = property.id
                                                        OR stored_vehicle_user.property_id IN (
                                                            SELECT stored_property_property.property_two_id
                                                            FROM property_property stored_property_property
                                                            WHERE stored_property_property.property_one_id = property.id
                                                        )
                                                    )
                                                    AND NOT EXISTS (
                                                        SELECT 1
                                                        FROM vehicle_vehicle_class stored_vehicle_class
                                                        WHERE stored_vehicle_class.vehicle_id = stored_vehicle_user.vehicle_id
                                                        AND stored_vehicle_class.vehicle_class_id = " . VehicleClass::BICYCLES_ID . "
                                                    )
                                                ),
                                                0
                                            ) AS stored_vehicles,
                                            IF(
                                                property_user.owned = 1,
                                                (
                                                    SELECT COUNT(DISTINCT stored_vehicle_user.vehicle_id)
                                                    FROM vehicle_user stored_vehicle_user
                                                    WHERE stored_vehicle_user.user_id = :userId AND stored_vehicle_user.owned = 1
                                                    AND (
                                                        stored_vehicle_user.property_id = property.id
                                                        OR stored_vehicle_user.property_id IN (
                                                            SELECT stored_property_property.property_two_id
                                                            FROM property_property stored_property_property
                                                            WHERE stored_property_property.property_one_id = property.id
                                                        )
                                                    )
                                                    AND EXISTS (
                                                        SELECT 1
                                                        FROM vehicle_vehicle_class stored_vehicle_class
                                                        WHERE stored_vehicle_class.vehicle_id = stored_vehicle_user.vehicle_id
                                                        AND stored_vehicle_class.vehicle_class_id = " . VehicleClass::BICYCLES_ID . "
                                                    )
                                                ),
                                                0
                                            ) AS stored_bicycles,
                                            IF(
                                                property_user.owned = 1,
                                                (
                                                    SELECT SUM(stored_price_vehicle.price)
                                                    FROM vehicle_user stored_price_vehicle_user
                                                    INNER JOIN vehicle stored_price_vehicle ON stored_price_vehicle.id = stored_price_vehicle_user.vehicle_id
                                                    WHERE stored_price_vehicle_user.user_id = :userId AND stored_price_vehicle_user.owned = 1
                                                    AND (
                                                        stored_price_vehicle_user.property_id = property.id
                                                        OR stored_price_vehicle_user.property_id IN (
                                                            SELECT stored_price_property_property.property_two_id
                                                            FROM property_property stored_price_property_property
                                                            WHERE stored_price_property_property.property_one_id = property.id
                                                        )
                                                    )
                                                ),
                                                0
                                            ) AS stored_price
            ";
        } elseif ($sum !== '') {
            $query = "
                SELECT SUM(s." . $sum . ") AS sum
                FROM ( SELECT DISTINCT property.id, property.capacity_vehicles, property.capacity_bicycles, property_user.owned, property_user.wanted,
                                       property.price,
                                       (
                                           SELECT SUM(vehicle_price.price)
                                           FROM vehicle_user vehicle_user_price
                                           INNER JOIN vehicle vehicle_price ON vehicle_price.id = vehicle_user_price.vehicle_id
                                           WHERE vehicle_user_price.user_id = :userId AND vehicle_user_price.owned = 1
                                           AND vehicle_user_price.property_id = property.id
                                       ) AS vehicles_price
            ";
        } else {
            $query = "
                SELECT property.id, CONCAT('uploads/property/', property.path) AS webPath, property.name, property_user.owned, property_user.wanted,
                property_type.id AS propertyTypeId, property_type_locale.name AS propertyTypeName, business_locale.name AS businessName,
                property_area.name AS propertyAreaName, property_location.name AS propertyLocationName, title_update.name AS titleUpdateName, property.price,
                property.discounted_price AS discountedPrice, property.discounted_percentage AS discountedPercentage,
                property.capacity_vehicles AS capacityVehicles, property.capacity_bicycles AS capacityBicycles, property.url, business.id AS businessId,
                business.maximum_number AS businessMaximumNumber,
                (
                    SELECT GROUP_CONCAT(parent_property.name ORDER BY parent_property.name SEPARATOR ', ')
                    FROM property_property parent_property_property
                    INNER JOIN property parent_property ON parent_property.id = parent_property_property.property_one_id
                    WHERE parent_property_property.property_two_id = property.id
                ) AS parentPropertyNames,
                (
                    SELECT COUNT(*)
                    FROM property_property parent_property_property
                    INNER JOIN property_user parent_property_user
                        ON parent_property_user.property_id = parent_property_property.property_one_id AND parent_property_user.user_id = :userId
                        AND parent_property_user.owned = 1
                    WHERE parent_property_property.property_two_id = property.id
                ) > 0 AS parentPropertyOwned,
                (
                    SELECT COUNT(DISTINCT business_property.id)
                    FROM property business_property
                    INNER JOIN property_user business_property_user
                        ON business_property_user.property_id = business_property.id AND business_property_user.user_id = :userId AND business_property_user.owned = 1
                    WHERE business_property.business_id = property.business_id
                    AND business_property.id NOT IN (SELECT business_property_property.property_two_id FROM property_property business_property_property)
                ) AS businessOwned,
                (
                    SELECT COUNT(DISTINCT business_property.id)
                    FROM property business_property
                    INNER JOIN property_user business_property_user
                        ON business_property_user.property_id = business_property.id AND business_property_user.user_id = :userId
                        AND business_property_user.wanted = 1
                    WHERE business_property.business_id = property.business_id
                    AND business_property.id NOT IN (SELECT business_property_property.property_two_id FROM property_property business_property_property)
                ) AS businessWanted,
                (
                    SELECT SUM(vehicle_price.price)
                    FROM vehicle_user vehicle_user_price
                    INNER JOIN vehicle vehicle_price ON vehicle_price.id = vehicle_user_price.vehicle_id
                    WHERE vehicle_user_price.user_id = :userId AND vehicle_user_price.owned = 1 AND vehicle_user_price.property_id = property.id
                ) AS vehiclesPrice,
                GROUP_CONCAT(DISTINCT property_acquisition_locale.name ORDER BY property_property_acquisition.position ASC SEPARATOR '+') AS propertyAcquisitionNames,
                GROUP_CONCAT(DISTINCT property_two.name ORDER BY property_property.position ASC SEPARATOR '+') AS propertyTwoNames,
                property_locale.comment, GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_two.id ASC SEPARATOR '+') AS propertyTwoIds,
                GROUP_CONCAT(
                    DISTINCT NULLIF(CONCAT_WS(' ', vehicle_manufacturer.name, vehicle.name), '')
                    ORDER BY
                        (
                            IF(vehicle.speed IS NOT NULL, 1, 0) +
                            IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                            IF(vehicle.braking IS NOT NULL, 1, 0) +
                            IF(vehicle.handling IS NOT NULL, 1, 0)
                        ) = 0 DESC,
                        (
                            (
                                COALESCE(vehicle.speed, 0) +
                                COALESCE(vehicle.acceleration, 0) +
                                COALESCE(vehicle.braking, 0) +
                                COALESCE(vehicle.handling, 0)
                            ) * 1.0
                        ) / NULLIF(
                            (
                                IF(vehicle.speed IS NOT NULL, 1, 0) +
                                IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                                IF(vehicle.braking IS NOT NULL, 1, 0) +
                                IF(vehicle.handling IS NOT NULL, 1, 0)
                            ),
                            0
                        ) DESC
                    SEPARATOR '+'
                ) AS vehicleNames
            ";
        }

        $query .= "
            FROM property
            LEFT JOIN property_locale ON property_locale.property_id = property.id AND property_locale.locale_id = :localeId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale
                ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            WHERE
        ";

        if (!in_array($sum, ['capacity_vehicles', 'capacity_bicycles', 'vehicles_price'], true)) {
            $query .= " property.id NOT IN (SELECT DISTINCT(property_property.property_two_id) FROM property_property) AND";
        }

        $query .= " property.id IN (
                SELECT /*+ NO_SEMIJOIN() */ DISTINCT property_search.id
                FROM property AS property_search
                LEFT JOIN property_property_acquisition AS property_property_acquisition_search
                    ON property_property_acquisition_search.property_id = property_search.id
                LEFT JOIN property_acquisition property_acquisition_search
                    ON property_acquisition_search.id = property_property_acquisition_search.property_acquisition_id
                LEFT JOIN property_acquisition_locale property_acquisition_search_locale
                    ON property_acquisition_search_locale.property_acquisition_id = property_acquisition_search.id
                    AND property_acquisition_search_locale.locale_id = :localeId
                LEFT JOIN vehicle_user vehicle_search_user ON vehicle_search_user.property_id = property_search.id AND vehicle_search_user.user_id = :userId
                LEFT JOIN vehicle vehicle_search ON vehicle_search_user.vehicle_id = vehicle_search.id
                LEFT JOIN vehicle_user vehicle_two_search_user
                    ON vehicle_two_search_user.property_id = property_search.id AND vehicle_two_search_user.user_id = :userId
                LEFT JOIN vehicle vehicle_two_search ON vehicle_two_search_user.vehicle_id = vehicle_two_search.id
                LEFT JOIN property_type_locale property_type_search_locale
                    ON property_type_search_locale.property_type_id = property_search.property_type_id AND property_type_search_locale.locale_id = :localeId
                LEFT JOIN business_locale business_search_locale
                    ON business_search_locale.business_id = property_search.business_id AND business_search_locale.locale_id = :localeId
                LEFT JOIN property_area property_area_search ON property_area_search.id = property_search.property_area_id
                LEFT JOIN property_location property_location_search ON property_location_search.id = property_search.property_location_id
                LEFT JOIN title_update title_update_search ON title_update_search.id = property_search.title_update_id
                LEFT JOIN property_locale property_search_locale
                    ON property_search_locale.property_id = property_search.id AND property_search_locale.locale_id = :localeId
        ";

        if ($values !== []) {
            foreach ($values as $key => $value) {
                if ($key) {
                    $query .= "
                        AND (
                            property_search.name LIKE :search_" . $key . "
                            OR property_type_search_locale.name LIKE :search_" . $key . "
                            OR business_search_locale.name LIKE :search_" . $key . "
                            OR property_area_search.name LIKE :search_" . $key . "
                            OR property_location_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR property_search.price LIKE :search_" . $key . "
                            OR property_search.discounted_price LIKE :search_" . $key . "
                            OR property_search.discounted_percentage LIKE :search_" . $key . "
                            OR property_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR property_search_locale.comment LIKE :search_" . $key . "
                            OR vehicle_search.name LIKE :search_" . $key . "
                            OR vehicle_two_search.name LIKE :search_" . $key . "
                        )
                    ";
                } else {
                    $query .= "
                        WHERE (
                            property_search.name LIKE :search_" . $key . "
                            OR property_type_search_locale.name LIKE :search_" . $key . "
                            OR business_search_locale.name LIKE :search_" . $key . "
                            OR property_area_search.name LIKE :search_" . $key . "
                            OR property_location_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR property_search.price LIKE :search_" . $key . "
                            OR property_search.discounted_price LIKE :search_" . $key . "
                            OR property_search.discounted_percentage LIKE :search_" . $key . "
                            OR property_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR property_search_locale.comment LIKE :search_" . $key . "
                            OR vehicle_search.name LIKE :search_" . $key . "
                            OR vehicle_two_search.name LIKE :search_" . $key . "
                        )
                    ";
                }
            }

            $hasValues = true;
        }

        if (!empty($filter['propertyAcquisitionIds'])) {
            foreach ($filter['propertyAcquisitionIds'] as $key => $propertyAcquisitionId) {
                if ($key) {
                    $query .= " OR property_property_acquisition_search.property_acquisition_id = :propertyAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (property_property_acquisition_search.property_acquisition_id = :propertyAcquisitionId_" . $key;
                    } else {
                        $query .= " WHERE (property_property_acquisition_search.property_acquisition_id = :propertyAcquisitionId_" . $key;
                    }
                }
            }

            $query .= ")";
        }

        $query .= ")";

        if (!in_array($sum, ['', 'wanted', 'summary'], true)) {
            $query .= " AND property_user.owned = 1";
        } elseif ($filter['owned'] !== null) {
            $query .= " AND property_user.owned = :owned";
        }

        if ($filter['wanted'] !== null) {
            $query .= " AND property_user.wanted = :wanted";
        }

        if ($filter['propertyTypeId'] !== 0) {
            $query .= " AND property_type.id = :propertyTypeId";
        }

        if ($filter['businessId'] !== 0) {
            $query .= " AND business.id = :businessId";
        }

        if ($filter['propertyAreaId'] !== 0) {
            $query .= " AND property_area.id = :propertyAreaId";
        }

        if ($filter['propertyLocationId'] !== 0) {
            $query .= " AND property_location.id = :propertyLocationId";
        }

        if ($filter['titleUpdateId'] !== 0) {
            $query .= " AND title_update.id = :titleUpdateId";
        }

        if ($filter['capacityVehicles'] !== null) {
            $query .= " AND property.capacity_vehicles = :capacityVehicles";
        }

        if ($filter['capacityBicycles'] !== null) {
            $query .= " AND property.capacity_bicycles = :capacityBicycles";
        }

        $query .= $count || $sum !== '' ? "" : " GROUP BY property.id";

        if (!$count && $sum === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order'], ['property-vehicles_price' => 'vehiclesPrice']);
        }

        $query .= $count || $sum !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        if ($sum === 'summary') {
            $query .= ") s GROUP BY s.groupKey) h) g";
        } elseif ($sum !== '') {
            $query .= ") s";
        }

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if (in_array($sum, ['', 'wanted', 'summary'], true) && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if ($filter['wanted'] !== null) {
            $statement->bindValue('wanted', $filter['wanted']);
        }

        if ($filter['propertyTypeId'] !== 0) {
            $statement->bindValue('propertyTypeId', $filter['propertyTypeId']);
        }

        if ($filter['businessId'] !== 0) {
            $statement->bindValue('businessId', $filter['businessId']);
        }

        if ($filter['propertyAreaId'] !== 0) {
            $statement->bindValue('propertyAreaId', $filter['propertyAreaId']);
        }

        if ($filter['propertyLocationId'] !== 0) {
            $statement->bindValue('propertyLocationId', $filter['propertyLocationId']);
        }

        if ($filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($filter['capacityVehicles'] !== null) {
            $statement->bindValue('capacityVehicles', $filter['capacityVehicles']);
        }

        if ($filter['capacityBicycles'] !== null) {
            $statement->bindValue('capacityBicycles', $filter['capacityBicycles']);
        }

        if (!empty($filter['propertyAcquisitionIds'])) {
            foreach ($filter['propertyAcquisitionIds'] as $key => $propertyAcquisitionId) {
                $statement->bindValue('propertyAcquisitionId_' . $key, $propertyAcquisitionId);
            }
        }

        if ($count) {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        if ($sum === 'summary') {
            return $statement->executeQuery()->fetchAssociative();
        }

        if ($sum !== '') {
            return $statement->executeQuery()->fetchAssociative()['sum'] ?? 0;
        }

        return $this->configurationHelper->removeExternalData($this->addPropertyTwos($statement->executeQuery()->fetchAllAssociative()));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPropertiesByIds(array $propertyIds): array
    {
        $query = "
            SELECT property.id, property.name, property.property_type_id AS propertyTypeId, business.id AS businessId, business_locale.name AS businessName,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles, property_user.owned,
                   property_user.wanted, property_type.id AS propertyTypeId, property_type_locale.name AS propertyTypeName,
                   property_area.id AS propertyAreaId, property_area.name AS propertyAreaName, property_location.id AS propertyLocationId,
                   property_location.name AS propertyLocationName, title_update.id AS titleUpdateId, title_update.name AS titleUpdateName, property.price,
                   property.discounted_price AS discountedPrice, property.discounted_percentage AS discountedPercentage, property.url,
                   COALESCE(property.capacity_vehicles, 0) AS capacityVehicles, COALESCE(property.capacity_bicycles, 0) AS capacityBicycles,
                   business.maximum_number AS businessMaximumNumber,
                   (
                       SELECT COUNT(DISTINCT business_property.id)
                       FROM property business_property
                       INNER JOIN property_user business_property_user
                           ON business_property_user.property_id = business_property.id AND business_property_user.user_id = :userId AND business_property_user.owned = 1
                       WHERE business_property.business_id = property.business_id
                       AND business_property.id NOT IN (SELECT business_property_property.property_two_id FROM property_property business_property_property)
                   ) AS businessOwned,
                   (
                       SELECT COUNT(DISTINCT business_property.id)
                       FROM property business_property
                       INNER JOIN property_user business_property_user
                           ON business_property_user.property_id = business_property.id AND business_property_user.user_id = :userId
                           AND business_property_user.wanted = 1
                       WHERE business_property.business_id = property.business_id
                       AND business_property.id NOT IN (SELECT business_property_property.property_two_id FROM property_property business_property_property)
                   ) AS businessWanted,
                   (
                       SELECT SUM(vehicle_price.price)
                       FROM vehicle_user vehicle_user_price
                       INNER JOIN vehicle vehicle_price ON vehicle_price.id = vehicle_user_price.vehicle_id
                       WHERE vehicle_user_price.user_id = :userId AND vehicle_user_price.owned = 1 AND vehicle_user_price.property_id = property.id
                   ) AS vehiclesPrice,
                   GROUP_CONCAT(DISTINCT property_acquisition.id ORDER BY property_property_acquisition.position ASC SEPARATOR '+') AS propertyAcquisitionIds,
                   GROUP_CONCAT(DISTINCT property_acquisition_locale.name ORDER BY property_property_acquisition.position ASC SEPARATOR '+') AS propertyAcquisitionNames,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_property.position ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds,
                   GROUP_CONCAT(
                       DISTINCT NULLIF(CONCAT_WS(' ', vehicle_manufacturer.name, vehicle.name), '')
                       ORDER BY
                           (
                               IF(vehicle.speed IS NOT NULL, 1, 0) +
                               IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                               IF(vehicle.braking IS NOT NULL, 1, 0) +
                               IF(vehicle.handling IS NOT NULL, 1, 0)
                           ) = 0 DESC,
                           (
                               (
                                   COALESCE(vehicle.speed, 0) +
                                   COALESCE(vehicle.acceleration, 0) +
                                   COALESCE(vehicle.braking, 0) +
                                   COALESCE(vehicle.handling, 0)
                               ) * 1.0
                           ) / NULLIF(
                               (
                                   IF(vehicle.speed IS NOT NULL, 1, 0) +
                                   IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                                   IF(vehicle.braking IS NOT NULL, 1, 0) +
                                   IF(vehicle.handling IS NOT NULL, 1, 0)
                               ),
                               0
                           ) DESC
                       SEPARATOR '+'
                   ) AS vehicleNames,
                   property_locale.comment
            FROM property
            LEFT JOIN property_locale ON property_locale.property_id = property.id AND property_locale.locale_id = :localeId
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            WHERE property.id IN (:propertyIds)
            GROUP BY property.id
        ";

        if ($propertyIds === []) {
            return [];
        }

        $properties = $this->entityManager->getConnection()->executeQuery(
            $query,
            [
                'bicycleClassId' => VehicleClass::BICYCLES_ID,
                'localeId'       => $this->security->getUser()->getLocale()->id,
                'propertyIds'    => array_map(intval(...), $propertyIds),
                'userId'         => $this->security->getUser()->id
            ],
            ['propertyIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        return array_column($this->configurationHelper->removeExternalData($properties), null, 'id');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getProperty(int $propertyId): array|false
    {
        return $this->getPropertiesByIds([$propertyId])[$propertyId] ?? false;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getSummary(array $filter): array
    {
        return $this->getProperties($filter, false, 'summary');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getProperties($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getUrls(): array
    {
        $query = "SELECT property.url FROM property WHERE property.url IS NOT NULL";

        $statement = $this->entityManager->getConnection()->prepare($query);

        return $statement->executeQuery()->fetchFirstColumn();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWantedProperty(int $propertyId): array|int
    {
        $query = "
            SELECT property.id, property.name, property.property_type_id AS propertyTypeId, business_locale.name AS businessName,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id <> :bicycleClassId THEN vehicle.id END) AS numberVehiclesNotBicycles,
                   COUNT(DISTINCT CASE WHEN vehicle_class.id = :bicycleClassId THEN vehicle.id END) AS numberVehiclesBicycles, property_user.owned,
                   property_user.wanted, property_type.id AS propertyTypeId, property_type_locale.name AS propertyTypeName,
                   property_area.id AS propertyAreaId, property_area.name AS propertyAreaName, property_location.id AS propertyLocationId,
                   property_location.name AS propertyLocationName, title_update.id AS titleUpdateId, title_update.name AS titleUpdateName, property.price,
                   property.discounted_price AS discountedPrice, property.discounted_percentage AS discountedPercentage,
                   COALESCE(property.capacity_vehicles, 0) AS capacityVehicles, COALESCE(property.capacity_bicycles, 0) AS capacityBicycles,
                   GROUP_CONCAT(DISTINCT property_acquisition.id ORDER BY property_property_acquisition.position ASC SEPARATOR '+') AS propertyAcquisitionIds,
                   GROUP_CONCAT(DISTINCT property_acquisition_locale.name ORDER BY property_property_acquisition.position ASC SEPARATOR '+') AS propertyAcquisitionNames,
                   GROUP_CONCAT(DISTINCT property_two.id ORDER BY property_property.position ASC SEPARATOR '+') AS propertyTwoIds,
                   GROUP_CONCAT(DISTINCT property_type_storage_location.storage_location_id ORDER BY property_type_storage_location.storage_location_id ASC SEPARATOR ',') AS storageLocationIds,
                   GROUP_CONCAT(
                       DISTINCT vehicle.name
                       ORDER BY
                           (
                               IF(vehicle.speed IS NOT NULL, 1, 0) +
                               IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                               IF(vehicle.braking IS NOT NULL, 1, 0) +
                               IF(vehicle.handling IS NOT NULL, 1, 0)
                           ) = 0 DESC,
                           (
                               (
                                   COALESCE(vehicle.speed, 0) +
                                   COALESCE(vehicle.acceleration, 0) +
                                   COALESCE(vehicle.braking, 0) +
                                   COALESCE(vehicle.handling, 0)
                               ) * 1.0
                           ) / NULLIF(
                               (
                                   IF(vehicle.speed IS NOT NULL, 1, 0) +
                                   IF(vehicle.acceleration IS NOT NULL, 1, 0) +
                                   IF(vehicle.braking IS NOT NULL, 1, 0) +
                                   IF(vehicle.handling IS NOT NULL, 1, 0)
                               ),
                               0
                           ) DESC
                       SEPARATOR '+'
                   ) AS vehicleNames,
                   property_locale.comment
            FROM property
            LEFT JOIN property_locale ON property_locale.property_id = property.id AND property_locale.locale_id = :localeId
            LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
            LEFT JOIN vehicle_user ON vehicle_user.wanted_property_id = property.id AND vehicle_user.user_id = :userId
            LEFT JOIN vehicle ON vehicle_user.vehicle_id = vehicle.id
            LEFT JOIN vehicle_vehicle_class ON vehicle_vehicle_class.vehicle_id = vehicle.id
            LEFT JOIN vehicle_class ON vehicle_class.id = vehicle_vehicle_class.vehicle_class_id
            LEFT JOIN vehicle_class_locale ON vehicle_class_locale.vehicle_class_id = vehicle_class.id AND vehicle_class_locale.locale_id = :localeId
            LEFT JOIN property_type ON property_type.id = property.property_type_id
            LEFT JOIN property_type_locale ON property_type_locale.property_type_id = property_type.id AND property_type_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = property.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            LEFT JOIN property_type_storage_location ON property_type_storage_location.property_type_id = property_type.id
            LEFT JOIN property_area ON property_area.id = property.property_area_id
            LEFT JOIN property_location ON property_location.id = property.property_location_id
            LEFT JOIN title_update ON title_update.id = property.title_update_id
            LEFT JOIN property_property_acquisition ON property_property_acquisition.property_id = property.id
            LEFT JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id
            LEFT JOIN property_acquisition_locale ON property_acquisition_locale.property_acquisition_id = property_acquisition.id AND property_acquisition_locale.locale_id = :localeId
            LEFT JOIN property_property ON property_property.property_one_id = property.id
            LEFT JOIN property property_two ON property_two.id = property_property.property_two_id
            WHERE property.id = :propertyId
            GROUP BY property.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('bicycleClassId', VehicleClass::BICYCLES_ID);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('propertyId', $propertyId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    private function addValues($statement, array $filter, string $type): void
    {
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($type !== 'Owned' && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if ($type !== 'Wanted' && $filter['wanted'] !== null) {
            $statement->bindValue('wanted', $filter['wanted']);
        }

        if ($type !== 'PropertyTypeId' && $filter['propertyTypeId'] !== 0) {
            $statement->bindValue('propertyTypeId', $filter['propertyTypeId']);
        }

        if ($type !== 'BusinessId' && $filter['businessId'] !== 0) {
            $statement->bindValue('businessId', $filter['businessId']);
        }

        if ($type !== 'PropertyAreaId' && $filter['propertyAreaId'] !== 0) {
            $statement->bindValue('propertyAreaId', $filter['propertyAreaId']);
        }

        if ($type !== 'PropertyLocationId' && $filter['propertyLocationId'] !== 0) {
            $statement->bindValue('propertyLocationId', $filter['propertyLocationId']);
        }

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($type !== 'CapacityVehicles' && $filter['capacityVehicles'] !== null) {
            $statement->bindValue('capacityVehicles', $filter['capacityVehicles']);
        }

        if ($type !== 'CapacityBicycles' && $filter['capacityBicycles'] !== null) {
            $statement->bindValue('capacityBicycles', $filter['capacityBicycles']);
        }

        if ($type !== 'PropertyAcquisitionId' && !empty($filter['propertyAcquisitionIds'])) {
            foreach ($filter['propertyAcquisitionIds'] as $key => $propertyAcquisitionId) {
                $statement->bindValue('propertyAcquisitionId_' . $key, $propertyAcquisitionId);
            }
        }
    }

    private function getQuery(array $filter, string $type, bool $verifyValues = false): string
    {
        $query     = '';
        $hasValues = !$verifyValues;

        if ($type !== 'Owned' && $filter['owned'] !== null) {
            if ($hasValues) {
                $query .= " AND property_user.owned = :owned";
            } else {
                $query     .= " WHERE property_user.owned = :owned";
                $hasValues = true;
            }
        }

        if ($type !== 'Wanted' && $filter['wanted'] !== null) {
            if ($hasValues) {
                $query .= " AND property_user.wanted = :wanted";
            } else {
                $query     .= " WHERE property_user.wanted = :wanted";
                $hasValues = true;
            }
        }

        if ($type !== 'PropertyTypeId' && $filter['propertyTypeId'] !== 0) {
            if ($hasValues) {
                $query .= " AND property_type.id = :propertyTypeId";
            } else {
                $query     .= " WHERE property_type.id = :propertyTypeId";
                $hasValues = true;
            }
        }

        if ($type !== 'BusinessId' && $filter['businessId'] !== 0) {
            if ($hasValues) {
                $query .= " AND business.id = :businessId";
            } else {
                $query     .= " WHERE business.id = :businessId";
                $hasValues = true;
            }
        }

        if ($type !== 'PropertyAreaId' && $filter['propertyAreaId'] !== 0) {
            if ($hasValues) {
                $query .= " AND property_area.id = :propertyAreaId";
            } else {
                $query     .= " WHERE property_area.id = :propertyAreaId";
                $hasValues = true;
            }
        }

        if ($type !== 'PropertyLocationId' && $filter['propertyLocationId'] !== 0) {
            if ($hasValues) {
                $query .= " AND property_location.id = :propertyLocationId";
            } else {
                $query     .= " WHERE property_location.id = :propertyLocationId";
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

        if ($type !== 'CapacityVehicles' && $filter['capacityVehicles'] !== null) {
            if ($hasValues) {
                $query .= " AND property.capacity_vehicles = :capacityVehicles";
            } else {
                $query     .= " WHERE property.capacity_vehicles = :capacityVehicles";
                $hasValues = true;
            }
        }

        if ($type !== 'CapacityBicycles' && $filter['capacityBicycles'] !== null) {
            if ($hasValues) {
                $query .= " AND property.capacity_bicycles = :capacityBicycles";
            } else {
                $query     .= " WHERE property.capacity_bicycles = :capacityBicycles";
                $hasValues = true;
            }
        }

        if ($type !== 'PropertyAcquisitionId' && !empty($filter['propertyAcquisitionIds'])) {
            foreach ($filter['propertyAcquisitionIds'] as $key => $propertyAcquisitionId) {
                if ($key) {
                    $query .= " OR property_property_acquisition.property_acquisition_id = :propertyAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (property_property_acquisition.property_acquisition_id = :propertyAcquisitionId_" . $key;
                    } else {
                        $query .= " WHERE (property_property_acquisition.property_acquisition_id = :propertyAcquisitionId_" . $key;
                    }
                }
            }

            $query .= ")";
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
        if ($filter['page'] > 1 && $this->getProperties($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('property', $filter);
        }

        return $filter;
    }
}

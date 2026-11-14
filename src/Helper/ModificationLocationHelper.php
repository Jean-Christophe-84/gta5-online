<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ModificationLocationHelper
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
    public function delete(int $modificationLocationId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM modification_location_locale WHERE modification_location_id = :id",
                "DELETE FROM modification_location WHERE id = :id"
            ],
            $modificationLocationId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $modificationLocationId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'vehicles' => "SELECT vehicle.name FROM vehicle_modification_location INNER JOIN vehicle ON vehicle.id = vehicle_modification_location.vehicle_id WHERE vehicle_modification_location.modification_location_id = :id ORDER BY vehicle.name"
                ],
                $modificationLocationId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('modificationLocation')) {
            return ['search' => '', 'page' => 1, 'order' => ['modification_location_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getModificationLocation(int $modificationLocationId): array|int
    {
        $query = "
            SELECT modification_location_locale.modification_location_id AS id, modification_location_locale.name
            FROM modification_location_locale
            WHERE modification_location_locale.modification_location_id = :modificationLocationId AND modification_location_locale.locale_id = :localeId
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('modificationLocationId', $modificationLocationId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getModificationLocations(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT modification_location_locale.modification_location_id) AS count";
        } else {
            $query = "SELECT modification_location_locale.modification_location_id AS id, modification_location_locale.name";
        }

        $query .= " FROM modification_location_locale WHERE modification_location_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND modification_location_locale.name LIKE :search_" . $key;
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
        return $this->configurationHelper->formatNumber($this->getModificationLocations($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getModificationLocations($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('modificationLocation', $filter);
        }

        return $filter;
    }
}

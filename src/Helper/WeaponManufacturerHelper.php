<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class WeaponManufacturerHelper
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
    public function delete(int $weaponManufacturerId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM weapon_manufacturer WHERE id = :id"
            ],
            $weaponManufacturerId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $weaponManufacturerId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'weapons' => "SELECT COALESCE(weapon_locale.name, weapon.id) FROM weapon LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId WHERE weapon.weapon_manufacturer_id = :id ORDER BY 1"
                ],
                $weaponManufacturerId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('weaponManufacturer')) {
            return ['search' => '', 'page' => 1, 'order' => ['weapon_manufacturer-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getWeaponManufacturers($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeaponManufacturer(int $weaponManufacturerId): array|int
    {
        $query = "
            SELECT weapon_manufacturer.id, weapon_manufacturer.name
            FROM weapon_manufacturer
            WHERE weapon_manufacturer.id = :weaponManufacturerId
            GROUP BY weapon_manufacturer.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('weaponManufacturerId', $weaponManufacturerId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeaponManufacturers(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT weapon_manufacturer.id) AS count";
        } else {
            $query = "SELECT weapon_manufacturer.id, weapon_manufacturer.name";
        }

        $query .= " FROM weapon_manufacturer";

        foreach ($values as $key => $value) {
            $query .= $key ? " AND weapon_manufacturer.name LIKE :search_" . $key : " WHERE weapon_manufacturer.name LIKE :search_" . $key;
        }

        $query .= $count ? "" : " GROUP BY weapon_manufacturer.id";

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
        if ($filter['page'] > 1 && $this->getWeaponManufacturers($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('weaponManufacturer', $filter);
        }

        return $filter;
    }
}

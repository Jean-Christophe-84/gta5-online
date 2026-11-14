<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class WeaponAcquisitionHelper
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
    public function delete(int $weaponAcquisitionId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM weapon_acquisition_locale WHERE weapon_acquisition_id = :id",
                "DELETE FROM weapon_acquisition WHERE id = :id"
            ],
            $weaponAcquisitionId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $weaponAcquisitionId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'weapons' => "SELECT COALESCE(weapon_locale.name, weapon.id) FROM weapon_weapon_acquisition INNER JOIN weapon ON weapon.id = weapon_weapon_acquisition.weapon_id LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId WHERE weapon_weapon_acquisition.weapon_acquisition_id = :id ORDER BY 1"
                ],
                $weaponAcquisitionId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('weaponAcquisition')) {
            return ['search' => '', 'page' => 1, 'order' => ['weapon_acquisition_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getWeaponAcquisitions($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeaponAcquisition(int $weaponAcquisitionId): array|int
    {
        $query = "
            SELECT weapon_acquisition_locale.weapon_acquisition_id AS id, weapon_acquisition_locale.name, weapon_acquisition.promotion
            FROM weapon_acquisition_locale
            INNER JOIN weapon_acquisition ON weapon_acquisition.id = weapon_acquisition_locale.weapon_acquisition_id
            WHERE weapon_acquisition_locale.weapon_acquisition_id = :weaponAcquisitionId AND weapon_acquisition_locale.locale_id = :localeId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('weaponAcquisitionId', $weaponAcquisitionId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeaponAcquisitions(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT weapon_acquisition_locale.weapon_acquisition_id) AS count";
        } else {
            $query = "SELECT weapon_acquisition_locale.weapon_acquisition_id AS id, weapon_acquisition_locale.name";
        }

        $query .= " FROM weapon_acquisition_locale WHERE weapon_acquisition_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND weapon_acquisition_locale.name LIKE :search_" . $key;
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
        if ($filter['page'] > 1 && $this->getWeaponAcquisitions($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('weaponAcquisition', $filter);
        }

        return $filter;
    }
}

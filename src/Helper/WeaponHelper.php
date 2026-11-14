<?php

namespace App\Helper;

use App\Entity\Weapon;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class WeaponHelper
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
    public function delete(int $weaponId): void
    {
        $path = $this->entityManager->getConnection()->fetchOne("SELECT path FROM weapon WHERE id = :id", ['id' => $weaponId]);

        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM weapon_locale WHERE weapon_id = :id",
                "DELETE FROM weapon_customization WHERE weapon_id = :id",
                "DELETE FROM weapon_weapon WHERE weapon_two_id = :id",
                "DELETE FROM weapon_weapon_acquisition WHERE weapon_id = :id",
                "DELETE FROM weapon_weapon_class WHERE weapon_id = :id",
                "DELETE FROM weapon_user WHERE weapon_id = :id",
                "DELETE FROM weapon WHERE id = :id"
            ],
            $weaponId
        );

        $this->configurationHelper->removeUpload('weapon', $path ? : null);
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $weaponId): array
    {
        $warnings = [];

        foreach ([
            'deletion_removed_from_users' => "SELECT COUNT(*) FROM weapon_user WHERE weapon_id = :id AND owned = 1"
        ] as $key => $query) {
            if ($count = (int)$this->entityManager->getConnection()->fetchOne($query, ['id' => $weaponId])) {
                $warnings[] = $this->configurationHelper->getTranslateLocale($key, ['count' => $count]);
            }
        }

        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'child_weapons' => "SELECT COALESCE(weapon_locale.name, weapon.id) FROM weapon_weapon INNER JOIN weapon ON weapon.id = weapon_weapon.weapon_two_id LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId WHERE weapon_weapon.weapon_one_id = :id ORDER BY 1",
                    'casino_gunmen' => "SELECT DISTINCT heist_casino_gunman.name FROM heist_casino_gunman_weapon INNER JOIN heist_casino_gunman ON heist_casino_gunman.id = heist_casino_gunman_weapon.heist_casino_gunman_id WHERE heist_casino_gunman_weapon.weapon_id = :id ORDER BY heist_casino_gunman.name"
                ],
                $weaponId
            ),
            'warnings' => $warnings
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getEvolutionLabelWeapon(int $evolution): string
    {
        $filter = match ($evolution) {
            Weapon::FILTER_EVOLUTION_ALL             => 'all',
            Weapon::FILTER_EVOLUTION_YES             => 'yes',
            Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS => 'yes_with_others',
            Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS  => 'no_with_others',
            Weapon::FILTER_EVOLUTION_NO              => 'no',
            default                                  => null,
        };

        if ($filter === null) {
            return '';
        }

        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('filter_evolution', ['entity' => 'weapons', 'filter' => $filter])
        );
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('weapon')) {
            return [
                'weaponManufacturerId'    => 0,
                'titleUpdateId'           => 0,
                'owned'                   => null,
                'weaponClassIds'          => [],
                'weaponAcquisitionIds'    => [],
                'weaponTwo'               => 0,
                'evolution'               => 0,
                'notWeaponClassIds'       => [],
                'notWeaponAcquisitionIds' => [],
                'search'                  => '',
                'page'                    => 1,
                'order'                   => ['weapon_locale-name' => 'ASC']
            ];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterEvolutions(array $filter, int $evolution): int
    {
        $query = "
            SELECT COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
        ";

        switch ($evolution) {
            case Weapon::FILTER_EVOLUTION_YES:
                $query .= " WHERE weapon.evolution = 1";
                break;
            case Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS:
                $query .= " WHERE weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                break;
            case Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS:
                $query .= " WHERE weapon.evolution = 0";
                break;
            case Weapon::FILTER_EVOLUTION_NO:
                $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                break;
        }

        $query .= $this->getQuery($filter, 'Evolution');

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'Evolution');

        return $statement->executeQuery()->fetchOne();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterNotWeaponAcquisitionIds(array $filter): array|int
    {
        $query = "
            SELECT weapon_acquisition_locale.name, weapon_acquisition.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale
                ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon_acquisition.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'NotWeaponAcquisitionId') . " GROUP BY weapon_acquisition.id ORDER BY weapon_acquisition_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'NotWeaponAcquisitionId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterNotWeaponClassIds(array $filter): array|int
    {
        $query = "
            SELECT weapon_class_locale.name, weapon_class.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_class_locale ON weapon_class_locale.weapon_class_id = weapon_class.id AND weapon_class_locale.locale_id = :localeId
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon_class.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'NotWeaponClassId') . " GROUP BY weapon_class.id ORDER BY weapon_class.position";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'NotWeaponClassId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterOwneds(array $filter): array
    {
        $query = "
            SELECT weapon_user.owned, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
        ";

        $query .= $this->getQuery($filter, 'Owned', true) . " GROUP BY weapon_user.owned ORDER BY weapon_user.owned";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'Owned');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterTitleUpdateIds(array $filter): array|int
    {
        $query = "
            SELECT title_update.name, title_update.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale
                ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
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
    public function getFilterWeaponAcquisitionIds(array $filter): array|int
    {
        $query = "
            SELECT weapon_acquisition_locale.name, weapon_acquisition.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon_acquisition.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'AcquisitionId') . " GROUP BY weapon_acquisition.id ORDER BY weapon_acquisition_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'AcquisitionId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterWeaponClassIds(array $filter): array|int
    {
        $query = "
            SELECT weapon_class_locale.name, weapon_class.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_class_locale ON weapon_class_locale.weapon_class_id = weapon_class.id AND weapon_class_locale.locale_id = :localeId
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon_class.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'WeaponClassId') . " GROUP BY weapon_class.id ORDER BY weapon_class.position";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'WeaponClassId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterWeaponManufacturerIds(array $filter): array|int
    {
        $query = "
            SELECT weapon_manufacturer.name, weapon_manufacturer.id, COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon_manufacturer.id IS NOT NULL
        ";

        $query .= $this->getQuery($filter, 'WeaponManufacturerId') . " GROUP BY weapon_manufacturer.id ORDER BY weapon_manufacturer.name";

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'WeaponManufacturerId');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterWeaponTwos(array $filter, int $weaponTwo): int
    {
        $query = "
            SELECT COUNT(DISTINCT weapon.id) AS numberWeapons
            FROM weapon
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
        ";

        switch ($weaponTwo) {
            case Weapon::FILTER_WEAPON_TWO_YES:
                $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                break;
            case Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS:
                $query .= " WHERE (weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) OR weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon))";
                break;
            case Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS:
                $query .= " WHERE weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                break;
            case Weapon::FILTER_WEAPON_TWO_NO:
                $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                break;
        }

        $query .= $this->getQuery($filter, 'WeaponTwo');

        $statement = $this->entityManager->getConnection()->prepare($query);

        $this->addValues($statement, $filter, 'WeaponTwo');

        return $statement->executeQuery()->fetchOne();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getNameAndAverageWeapon(int $weaponId): string
    {
        $query = "
            SELECT weapon_locale.name,
                (
                    (
                        COALESCE(weapon.damage, 0) +
                        COALESCE(weapon.fire_rate, 0) +
                        COALESCE(weapon.accuracy, 0) +
                        COALESCE(weapon.range_weapon, 0) +
                        COALESCE(weapon.clip_size, 0)
                    ) * 1.0
                ) / (
                    (IF(weapon.damage IS NOT NULL, 1, 0)) +
                    (IF(weapon.fire_rate IS NOT NULL, 1, 0)) +
                    (IF(weapon.accuracy IS NOT NULL, 1, 0)) +
                    (IF(weapon.range_weapon IS NOT NULL, 1, 0)) +
                    (IF(weapon.clip_size IS NOT NULL, 1, 0))
                ) AS average
            FROM weapon
            LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId
            WHERE weapon_locale.weapon_id = :weaponId
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('weaponId', $weaponId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative()['name'] . ' (' . $this->configurationHelper->formatPercent($statement->executeQuery()->fetchAssociative()['average'] ?? 0, 2) . ')';
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getParameters(array $filter): array
    {
        return [
            'filter'                        => $filter,
            'filterEvolutions'              => [
                ['evolution' => Weapon::FILTER_EVOLUTION_YES, 'numberWeapons' => $this->getFilterEvolutions($filter, Weapon::FILTER_EVOLUTION_YES)],
                [
                    'evolution'     => Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS,
                    'numberWeapons' => $this->getFilterEvolutions($filter, Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS)
                ],
                [
                    'evolution'     => Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS,
                    'numberWeapons' => $this->getFilterEvolutions($filter, Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS)
                ],
                ['evolution' => Weapon::FILTER_EVOLUTION_NO, 'numberWeapons' => $this->getFilterEvolutions($filter, Weapon::FILTER_EVOLUTION_NO)],
            ],
            'filterOwneds'                  => $this->getFilterOwneds($filter),
            'filterNotWeaponAcquisitionIds' => $this->getFilterNotWeaponAcquisitionIds($filter),
            'filterNotWeaponClassIds'       => $this->getFilterNotWeaponClassIds($filter),
            'filterTitleUpdateIds'          => $this->getFilterTitleUpdateIds($filter),
            'filterWeaponAcquisitionIds'    => $this->getFilterWeaponAcquisitionIds($filter),
            'filterWeaponClassIds'          => $this->getFilterWeaponClassIds($filter),
            'filterWeaponManufacturerIds'   => $this->getFilterWeaponManufacturerIds($filter),
            'filterWeaponTwos'              => [
                [
                    'weaponTwo'     => Weapon::FILTER_WEAPON_TWO_YES,
                    'numberWeapons' => $this->getFilterWeaponTwos($filter, Weapon::FILTER_WEAPON_TWO_YES)
                ],
                [
                    'weaponTwo'     => Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS,
                    'numberWeapons' => $this->getFilterWeaponTwos($filter, Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS)
                ],
                [
                    'weaponTwo'     => Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS,
                    'numberWeapons' => $this->getFilterWeaponTwos($filter, Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS)
                ],
                ['weaponTwo' => Weapon::FILTER_WEAPON_TWO_NO, 'numberWeapons' => $this->getFilterWeaponTwos($filter, Weapon::FILTER_WEAPON_TWO_NO)],
            ],
            'summary'                       => $this->security->isGranted(Weapon::PERMISSION_WEAPON_OWN) ? $this->getSummary($filter) : null,
            'totalNames'                    => $this->getTotalNames(),
            'weapons'                       => $this->getWeapons($filter)
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getSummary(array $filter): array
    {
        return $this->getWeapons($filter, '', 'summary');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getWeapons($this->getFilter($defaultFilter), 'id'));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getUrls(): array
    {
        $query = "SELECT weapon.url FROM weapon WHERE weapon.url IS NOT NULL";

        $statement = $this->entityManager->getConnection()->prepare($query);

        return $statement->executeQuery()->fetchFirstColumn();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeapon(int $weaponId): array|false
    {
        $query = "
            SELECT weapon.id, weapon.url, weapon.real_life_weapon AS realLifeWeapon, weapon_locale.name, weapon_manufacturer.id AS weaponManufacturerId,
                   weapon_manufacturer.name AS weaponManufacturerName, weapon.real_life_weapon AS realLifeWeapon, weapon_user.owned,
                   GROUP_CONCAT(DISTINCT weapon_one.id ORDER BY weapon_weapon.position ASC SEPARATOR '+') AS weaponOneIds,
                   GROUP_CONCAT(DISTINCT weapon_one_locale.name ORDER BY weapon_weapon.position ASC SEPARATOR '+') AS weaponOneNames,
                   GROUP_CONCAT(DISTINCT weapon_class.id ORDER BY weapon_weapon_class.position ASC SEPARATOR '+') AS weaponClassIds,
                   GROUP_CONCAT(DISTINCT weapon_class_locale.name ORDER BY weapon_weapon_class.position ASC SEPARATOR '+') AS weaponClassNames,
                   title_update.id AS titleUpdateId, weapon.price, weapon.discounted_price AS discountedPrice,
                   weapon.discounted_percentage AS discountedPercentage, weapon.damage, weapon.fire_rate AS fireRate, weapon.accuracy,
                   weapon.range_weapon AS rangeWeapon, weapon.clip_size AS clipSize,
                   GROUP_CONCAT(DISTINCT weapon_acquisition.id ORDER BY weapon_weapon_acquisition.position ASC SEPARATOR '+') AS weaponAcquisitionIds,
                   GROUP_CONCAT(DISTINCT weapon_acquisition_locale.name ORDER BY weapon_weapon_acquisition.position ASC SEPARATOR '+') AS weaponAcquisitionNames,
                   GROUP_CONCAT(DISTINCT customization.id ORDER BY weapon_customization.position ASC SEPARATOR '+') AS customizationIds,
                   GROUP_CONCAT(DISTINCT customization_locale.name ORDER BY weapon_customization.position ASC SEPARATOR '+') AS customizationNames,
                   weapon.unlock_rank AS unlockRank, weapon_locale.comment, weapon_locale.unlock_weapon AS unlockWeapon, weapon.evolution
            FROM weapon
            LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId
            LEFT JOIN weapon_user On weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_weapon ON weapon_weapon.weapon_two_id = weapon.id
            LEFT JOIN weapon weapon_one ON weapon_one.id = weapon_weapon.weapon_one_id
            LEFT JOIN weapon_locale weapon_one_locale ON weapon_one_locale.weapon_id = weapon_one.id AND weapon_one_locale.locale_id = :localeId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_class_locale ON weapon_class_locale.weapon_class_id = weapon_class.id AND weapon_class_locale.locale_id = :localeId
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
                ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale
                ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon.id = :weaponId
            GROUP BY weapon.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('weaponId', $weaponId);

        return $this->configurationHelper->removeExternalData([$statement->executeQuery()->fetchAssociative()])[0];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getWeapons(array $filter, string $count = '', string $sum = ''): array|float
    {
        $perPage   = $this->parameterBag->get('per_page');
        $values    = $filter['search'] ? explode(' ', $filter['search']) : [];
        $hasValues = false;

        if ($count !== '') {
            if ($count === 'owned') {
                $query = "SELECT COUNT(DISTINCT weapon_user." . $count . ") AS count";
            } else {
                $query = "SELECT COUNT(DISTINCT weapon." . $count . ") AS count";
            }
        } elseif ($sum === 'summary') {
            $query = "
                SELECT COUNT(g.groupId) AS total, COALESCE(SUM(g.groupOwned), 0) AS owned, COALESCE(SUM(g.ownedPrice), 0) AS ownedPrice,
                       COALESCE(SUM(g.groupOwned = 0), 0) AS toBuy, COALESCE(SUM(IF(g.groupOwned = 0, g.price, 0)), 0) AS toBuyPrice,
                       COALESCE(SUM(g.discounted), 0) AS discounted, COALESCE(SUM(IF(g.groupOwned = 0, g.discounted, 0)), 0) AS discountedNotOwned,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discounted, 0)), 0) AS toBuyDiscounted,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discountedPrice, 0)), 0) AS discountedPrice,
                       COALESCE(SUM(IF(g.groupOwned = 0, g.discountedBasePrice, 0)), 0) AS discountedBasePrice,
                       COALESCE(SUM(g.evolutionPrice IS NOT NULL), 0) AS evolutions, COALESCE(SUM(g.evolutionOwned), 0) AS evolutionsOwned,
                       COALESCE(SUM(IF(g.evolutionOwned = 0, g.evolutionPrice, 0)), 0) AS evolutionsToBuyPrice,
                       COALESCE(
                           SUM(
                               IF(
                                   g.evolutionPrice IS NOT NULL,
                                   IF(g.evolutionOwned = 1, 0, g.evolutionPrice + IF(g.baseOwned = 1, 0, COALESCE(g.basePrice, 0))),
                                   IF(g.groupOwned = 1, 0, g.price)
                               )
                           ),
                           0
                       ) AS allEvolvedPrice
                FROM (
                    SELECT s.groupId,
                           EXISTS (
                               SELECT 1
                               FROM weapon_user group_user
                               LEFT JOIN weapon group_weapon ON group_weapon.id = group_user.weapon_id
                               LEFT JOIN weapon_weapon group_weapon_weapon ON group_weapon_weapon.weapon_two_id = group_user.weapon_id
                               WHERE group_user.user_id = :userId AND group_user.owned = 1
                               AND (group_user.weapon_id = s.groupId OR (group_weapon.evolution = 1 AND group_weapon_weapon.weapon_one_id = s.groupId))
                           ) AS groupOwned,
                           EXISTS (
                               SELECT 1 FROM weapon_user base_user
                               WHERE base_user.user_id = :userId AND base_user.owned = 1 AND base_user.weapon_id = s.groupId
                           ) AS baseOwned,
                           (
                               SELECT base.price * (100 - COALESCE(base.discounted_percentage, 0)) / 100 FROM weapon base WHERE base.id = s.groupId
                           ) AS basePrice,
                           EXISTS (
                               SELECT 1
                               FROM weapon_weapon evolution_weapon_weapon
                               INNER JOIN weapon evolution ON evolution.id = evolution_weapon_weapon.weapon_two_id AND evolution.evolution = 1
                               INNER JOIN weapon_user evolution_user
                                   ON evolution_user.weapon_id = evolution.id AND evolution_user.user_id = :userId AND evolution_user.owned = 1
                               WHERE evolution_weapon_weapon.weapon_one_id = s.groupId
                           ) AS evolutionOwned,
                           (
                               SELECT MIN(evolution.price * (100 - COALESCE(evolution.discounted_percentage, 0)) / 100)
                               FROM weapon_weapon evolution_weapon_weapon
                               INNER JOIN weapon evolution ON evolution.id = evolution_weapon_weapon.weapon_two_id AND evolution.evolution = 1
                               WHERE evolution_weapon_weapon.weapon_one_id = s.groupId
                           ) AS evolutionPrice,
                           SUM(IF(s.owned = 1, s.price, 0)) AS ownedPrice,
                           MIN(s.price * (100 - COALESCE(s.discounted_percentage, 0)) / 100) AS price,
                           SUM(s.discounted_percentage IS NOT NULL) AS discounted,
                           SUM(IF(s.discounted_percentage IS NOT NULL, s.price * (100 - s.discounted_percentage) / 100, 0)) AS discountedPrice,
                           SUM(IF(s.discounted_percentage IS NOT NULL, s.price, 0)) AS discountedBasePrice
                    FROM (
                        SELECT DISTINCT weapon.id, weapon_user.owned, weapon.price, weapon.discounted_percentage,
                                        COALESCE(
                                            (
                                                SELECT weapon_weapon_group.weapon_one_id
                                                FROM weapon_weapon weapon_weapon_group
                                                WHERE weapon_weapon_group.weapon_two_id = weapon.id AND weapon.evolution = 1
                                                ORDER BY weapon_weapon_group.position
                                                LIMIT 1
                                            ),
                                            weapon.id
                                        ) AS groupId
            ";
        } elseif ($sum !== '') {
            $query = "SELECT SUM(s." . $sum . ") AS sum FROM ( SELECT DISTINCT weapon.id, weapon_user.owned, weapon.price";
        } else {
            $query = "
                SELECT weapon.id, CONCAT('uploads/weapon/', weapon.path) AS webPath, weapon.url, weapon.real_life_weapon AS realLifeWeapon, weapon_locale.name,
                GROUP_CONCAT(DISTINCT NULLIF(CONCAT_WS(' ', weapon_one_manufacturer.name, weapon_one_locale.name), '') ORDER BY weapon_weapon.position ASC SEPARATOR '+') AS weaponOneNames,
                weapon.evolution, weapon_manufacturer.name AS weaponManufacturerName, title_update.name AS titleUpdateName, weapon_user.owned,
                GROUP_CONCAT(DISTINCT weapon_class_locale.name ORDER BY weapon_weapon_class.position ASC SEPARATOR '+') AS weaponClassNames,
                weapon.price, weapon.discounted_price AS discountedPrice, weapon.discounted_percentage AS discountedPercentage,
                weapon.unlock_rank AS unlockRank, weapon_locale.comment, weapon_locale.unlock_weapon AS unlockWeapon, weapon.damage,
                weapon.fire_rate AS fireRate, weapon.accuracy, weapon.range_weapon AS rangeWeapon, weapon.clip_size AS clipSize,
                GROUP_CONCAT(DISTINCT weapon_acquisition_locale.name ORDER BY weapon_weapon_acquisition.position ASC SEPARATOR '+') AS weaponAcquisitionNames,
                GROUP_CONCAT(DISTINCT customization_locale.name ORDER BY weapon_customization.position ASC SEPARATOR '+') AS customizationNames
            ";
        }

        // @note: NO_SEMIJOIN garde la sous-requête de recherche à part ; fusionnée, ses jointures s'ajoutent à celles de la requête
        // et l'optimiseur MySQL met plusieurs secondes à choisir leur ordre
        $query .= "
            FROM weapon
            LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId
            LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
            LEFT JOIN weapon_weapon ON weapon_weapon.weapon_two_id = weapon.id
            LEFT JOIN weapon weapon_one ON weapon_one.id = weapon_weapon.weapon_one_id
            LEFT JOIN weapon_locale weapon_one_locale ON weapon_one_locale.weapon_id = weapon_one.id AND weapon_one_locale.locale_id = :localeId
            LEFT JOIN weapon_manufacturer ON weapon_manufacturer.id = weapon.weapon_manufacturer_id
            LEFT JOIN weapon_manufacturer weapon_one_manufacturer ON weapon_one_manufacturer.id = weapon_one.weapon_manufacturer_id
            LEFT JOIN title_update ON title_update.id = weapon.title_update_id
            LEFT JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
            LEFT JOIN weapon_class ON weapon_class.id = weapon_weapon_class.weapon_class_id
            LEFT JOIN weapon_class_locale ON weapon_class_locale.weapon_class_id = weapon_class.id AND weapon_class_locale.locale_id = :localeId
            LEFT JOIN weapon_weapon_acquisition ON weapon_weapon_acquisition.weapon_id = weapon.id
            LEFT JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id
            LEFT JOIN weapon_acquisition_locale
            ON weapon_acquisition_locale.weapon_acquisition_id = weapon_acquisition.id AND weapon_acquisition_locale.locale_id = :localeId
            LEFT JOIN weapon_customization ON weapon_customization.weapon_id = weapon.id
            LEFT JOIN customization ON customization.id = weapon_customization.customization_id
            LEFT JOIN customization_locale
                ON customization_locale.customization_id = customization.id AND customization_locale.locale_id = :localeId
            WHERE weapon.id IN (
                SELECT /*+ NO_SEMIJOIN() */ DISTINCT weapon_search.id
                FROM weapon AS weapon_search
                LEFT JOIN weapon_weapon_class weapon_weapon_class_search ON weapon_weapon_class_search.weapon_id = weapon_search.id
                LEFT JOIN weapon_class weapon_class_search ON weapon_class_search.id = weapon_weapon_class_search.weapon_class_id
                LEFT JOIN weapon_class_locale weapon_class_search_locale
                    ON weapon_class_search_locale.weapon_class_id = weapon_class_search.id AND weapon_class_search_locale.locale_id = :localeId
                LEFT JOIN weapon_weapon_acquisition weapon_weapon_acquisition_search ON weapon_weapon_acquisition_search.weapon_id = weapon_search.id
                LEFT JOIN weapon_acquisition weapon_acquisition_search ON weapon_acquisition_search.id = weapon_weapon_acquisition_search.weapon_acquisition_id
                LEFT JOIN weapon_acquisition_locale weapon_acquisition_search_locale
                    ON weapon_acquisition_search_locale.weapon_acquisition_id = weapon_acquisition_search.id
                    AND weapon_acquisition_search_locale.locale_id = :localeId
                LEFT JOIN weapon_customization weapon_customization_search ON weapon_customization_search.weapon_id = weapon_search.id
                LEFT JOIN customization customization_search ON customization_search.id = weapon_customization_search.customization_id
                LEFT JOIN customization_locale customization_search_locale
                    ON customization_search_locale.customization_id = customization_search.id AND customization_search_locale.locale_id = :localeId
                LEFT JOIN weapon_locale weapon_search_locale
                    ON weapon_search_locale.weapon_id = weapon_search.id AND weapon_search_locale.locale_id = :localeId
                LEFT JOIN weapon_manufacturer weapon_manufacturer_search ON weapon_manufacturer_search.id = weapon_search.weapon_manufacturer_id
                LEFT JOIN title_update title_update_search ON title_update_search.id = weapon_search.title_update_id
        ";

        if ($values !== []) {
            foreach ($values as $key => $value) {
                if ($key) {
                    $query .= "
                        AND (
                            weapon_search_locale.name LIKE :search_" . $key . "
                            OR weapon_search.real_life_weapon LIKE :search_" . $key . "
                            OR weapon_manufacturer_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR weapon_class_search_locale.name LIKE :search_" . $key . "
                            OR weapon_search.price LIKE :search_" . $key . "
                            OR weapon_search.discounted_price LIKE :search_" . $key . "
                            OR weapon_search.discounted_percentage LIKE :search_" . $key . "
                            OR weapon_search.unlock_rank LIKE :search_" . $key . "
                            OR weapon_search_locale.comment LIKE :search_" . $key . "
                            OR weapon_search_locale.unlock_weapon LIKE :search_" . $key . "
                            OR weapon_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR customization_search_locale.name LIKE :search_" . $key . "
                        )
                    ";
                } else {
                    $query .= "
                        WHERE (
                            weapon_search_locale.name LIKE :search_" . $key . "
                            OR weapon_search.real_life_weapon LIKE :search_" . $key . "
                            OR weapon_manufacturer_search.name LIKE :search_" . $key . "
                            OR title_update_search.name LIKE :search_" . $key . "
                            OR weapon_class_search_locale.name LIKE :search_" . $key . "
                            OR weapon_search.price LIKE :search_" . $key . "
                            OR weapon_search.discounted_price LIKE :search_" . $key . "
                            OR weapon_search.discounted_percentage LIKE :search_" . $key . "
                            OR weapon_search.unlock_rank LIKE :search_" . $key . "
                            OR weapon_search_locale.comment LIKE :search_" . $key . "
                            OR weapon_search_locale.unlock_weapon LIKE :search_" . $key . "
                            OR weapon_acquisition_search_locale.name LIKE :search_" . $key . "
                            OR customization_search_locale.name LIKE :search_" . $key . "
                        )
                    ";
                }
            }

            $hasValues = true;
        }

        if (!empty($filter['weaponClassIds'])) {
            foreach ($filter['weaponClassIds'] as $key => $weaponClassId) {
                if ($key) {
                    $query .= " OR weapon_class_search.id = :weaponClassId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (weapon_class_search.id = :weaponClassId_" . $key;
                    } else {
                        $query .= " WHERE (weapon_class_search.id = :weaponClassId_" . $key;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if (!empty($filter['weaponAcquisitionIds'])) {
            foreach ($filter['weaponAcquisitionIds'] as $key => $weaponAcquisitionId) {
                if ($key) {
                    $query .= " OR weapon_weapon_acquisition_search.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (weapon_weapon_acquisition_search.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                    } else {
                        $query .= " WHERE (weapon_weapon_acquisition_search.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                    }
                }
            }

            $query .= ")";
        }

        $query .= ")";

        if ($filter['weaponManufacturerId'] !== 0) {
            $query .= " AND weapon_manufacturer.id = :weaponManufacturerId";
        }

        if ($filter['titleUpdateId'] !== 0) {
            $query .= " AND title_update.id = :titleUpdateId";
        }

        if ($sum !== '' && $sum !== 'summary') {
            $query .= " AND weapon_user.owned = 1";
        } elseif ($filter['owned'] !== null) {
            $query .= " AND weapon_user.owned = :owned";
        }

        if ($filter['weaponTwo'] !== 0) {
            switch ($filter['weaponTwo']) {
                case Weapon::FILTER_WEAPON_TWO_YES:
                    $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                    break;
                case Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS:
                    $query .= " AND (weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) OR weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon))";
                    break;
                case Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS:
                    $query .= " AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                    break;
                case Weapon::FILTER_WEAPON_TWO_NO:
                    $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                    break;
            }
        }

        if ($filter['evolution'] !== 0) {
            switch ($filter['evolution']) {
                case Weapon::FILTER_EVOLUTION_YES:
                    $query .= " AND weapon.evolution = 1";
                    break;
                case Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS:
                    $query .= " AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                    break;
                case Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS:
                    $query .= " AND weapon.evolution = 0";
                    break;
                case Weapon::FILTER_EVOLUTION_NO:
                    $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                    break;
            }
        }

        if (!empty($filter['notWeaponClassIds'])) {
            foreach ($filter['notWeaponClassIds'] as $key => $notWeaponClassId) {
                $query .= "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM weapon_weapon_class weapon_weapon_class_not
                        WHERE weapon_weapon_class_not.weapon_id = weapon.id
                        AND weapon_weapon_class_not.weapon_class_id = :notWeaponClassId_" . $key . "
                    )
                ";
            }
        }

        if (!empty($filter['notWeaponAcquisitionIds'])) {
            foreach ($filter['notWeaponAcquisitionIds'] as $key => $notWeaponAcquisitionId) {
                $query .= "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM weapon_weapon_acquisition weapon_weapon_acquisition_not
                        WHERE weapon_weapon_acquisition_not.weapon_id = weapon.id
                        AND weapon_weapon_acquisition_not.weapon_acquisition_id = :notWeaponAcquisitionId_" . $key . "
                    )
                ";
            }
        }

        $query .= $count !== '' || $sum !== '' ? "" : " GROUP BY weapon.id";

        if ($count === '' && $sum === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy(
                $query, $filter['order'], [
                    'weapon-average' => '(
                        (
                            COALESCE(weapon.damage, 0) +
                            COALESCE(weapon.fire_rate, 0) +
                            COALESCE(weapon.accuracy, 0) +
                            COALESCE(weapon.range_weapon, 0) +
                            COALESCE(weapon.clip_size, 0)
                        ) * 1.0
                    ) / (
                        (IF(weapon.damage IS NOT NULL, 1, 0)) +
                        (IF(weapon.fire_rate IS NOT NULL, 1, 0)) +
                        (IF(weapon.accuracy IS NOT NULL, 1, 0)) +
                        (IF(weapon.range_weapon IS NOT NULL, 1, 0)) +
                        (IF(weapon.clip_size IS NOT NULL, 1, 0))
                    )'
                ]
            );
        }

        $query .= $count !== '' || $sum !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        if ($sum === 'summary') {
            $query .= ') s GROUP BY s.groupId) g';
        } elseif ($sum !== '') {
            $query .= ') s';
        }

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($filter['weaponManufacturerId'] !== 0) {
            $statement->bindValue('weaponManufacturerId', $filter['weaponManufacturerId']);
        }

        if ($filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if (($sum === '' || $sum === 'summary') && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if (!empty($filter['weaponClassIds'])) {
            foreach ($filter['weaponClassIds'] as $key => $weaponClassId) {
                $statement->bindValue('weaponClassId_' . $key, $weaponClassId);
            }
        }

        if (!empty($filter['weaponAcquisitionIds'])) {
            foreach ($filter['weaponAcquisitionIds'] as $key => $weaponAcquisitionId) {
                $statement->bindValue('weaponAcquisitionId_' . $key, $weaponAcquisitionId);
            }
        }

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if (!empty($filter['notWeaponClassIds'])) {
            foreach ($filter['notWeaponClassIds'] as $key => $notWeaponClassId) {
                $statement->bindValue('notWeaponClassId_' . $key, $notWeaponClassId);
            }
        }

        if (!empty($filter['notWeaponAcquisitionIds'])) {
            foreach ($filter['notWeaponAcquisitionIds'] as $key => $notWeaponAcquisitionId) {
                $statement->bindValue('notWeaponAcquisitionId_' . $key, $notWeaponAcquisitionId);
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
    public function getWeaponTwoLabelWeapon(int $weaponTwo): string
    {
        $filter = match ($weaponTwo) {
            Weapon::FILTER_WEAPON_TWO_ALL             => 'all',
            Weapon::FILTER_WEAPON_TWO_YES             => 'yes',
            Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS => 'yes_with_others',
            Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS  => 'no_with_others',
            Weapon::FILTER_WEAPON_TWO_NO              => 'no',
            default                                   => null,
        };

        if ($filter === null) {
            return '';
        }

        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('filter_two', ['entity' => 'weapons', 'filter' => $filter])
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function releaseLinkedEvolutions(int $weaponId): void
    {
        $query = "
            UPDATE weapon_user
            SET weapon_user.owned = 0, weapon_user.updated_at = :updatedAt, weapon_user.updated_by_id = :userId
            WHERE weapon_user.user_id = :userId
            AND weapon_user.weapon_id IN (
                SELECT weapon_weapon.weapon_one_id
                FROM weapon_weapon
                INNER JOIN weapon evolution ON evolution.id = weapon_weapon.weapon_two_id AND evolution.evolution = 1
                WHERE weapon_weapon.weapon_two_id = :weaponId
                UNION
                SELECT weapon_weapon.weapon_two_id
                FROM weapon_weapon
                INNER JOIN weapon evolution ON evolution.id = weapon_weapon.weapon_two_id AND evolution.evolution = 1
                WHERE weapon_weapon.weapon_one_id = :weaponId
            )
        ";

        $this->entityManager->getConnection()->executeStatement(
            $query, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $this->security->getUser()->id, 'weaponId' => $weaponId]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    private function addValues($statement, array $filter, string $type): void
    {
        $statement->bindValue('userId', $this->security->getUser()->id);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($type !== 'WeaponManufacturerId' && $filter['weaponManufacturerId'] !== 0) {
            $statement->bindValue('weaponManufacturerId', $filter['weaponManufacturerId']);
        }

        if ($type !== 'TitleUpdateId' && $filter['titleUpdateId'] !== 0) {
            $statement->bindValue('titleUpdateId', $filter['titleUpdateId']);
        }

        if ($type !== 'Owned' && $filter['owned'] !== null) {
            $statement->bindValue('owned', $filter['owned']);
        }

        if ($type !== 'WeaponClassId' && !empty($filter['weaponClassIds'])) {
            foreach ($filter['weaponClassIds'] as $key => $weaponClassId) {
                $statement->bindValue('weaponClassId_' . $key, $weaponClassId);
            }
        }

        if ($type !== 'WeaponAcquisitionId' && !empty($filter['weaponAcquisitionIds'])) {
            foreach ($filter['weaponAcquisitionIds'] as $key => $weaponAcquisitionId) {
                $statement->bindValue('weaponAcquisitionId_' . $key, $weaponAcquisitionId);
            }
        }

        if ($type !== 'NotWeaponClassId' && !empty($filter['notWeaponClassIds'])) {
            foreach ($filter['notWeaponClassIds'] as $key => $notWeaponClassId) {
                $statement->bindValue('notWeaponClassId_' . $key, $notWeaponClassId);
            }
        }

        if ($type !== 'NotWeaponAcquisitionId' && !empty($filter['notWeaponAcquisitionIds'])) {
            foreach ($filter['notWeaponAcquisitionIds'] as $key => $notWeaponAcquisitionId) {
                $statement->bindValue('notWeaponAcquisitionId_' . $key, $notWeaponAcquisitionId);
            }
        }
    }

    private function getQuery(array $filter, string $type, bool $verifyValues = false): string
    {
        $query     = '';
        $hasValues = !$verifyValues;

        if ($type !== 'WeaponManufacturerId' && $filter['weaponManufacturerId'] !== 0) {
            if ($hasValues) {
                $query .= " AND weapon_manufacturer.id = :weaponManufacturerId";
            } else {
                $query     .= " WHERE weapon_manufacturer.id = :weaponManufacturerId";
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
                $query .= " AND weapon_user.owned = :owned";
            } else {
                $query     .= " WHERE weapon_user.owned = :owned";
                $hasValues = true;
            }
        }

        if ($type !== 'WeaponClassId' && !empty($filter['weaponClassIds'])) {
            foreach ($filter['weaponClassIds'] as $key => $weaponClassId) {
                if ($key) {
                    $query .= " OR weapon_class.id = :weaponClassId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (weapon_class.id = :weaponClassId_" . $key;
                    } else {
                        $query     .= " WHERE (weapon_class.id = :weaponClassId_" . $key;
                        $hasValues = true;
                    }
                }
            }

            $query .= ")";
        }

        if ($type !== 'WeaponAcquisitionId' && !empty($filter['weaponAcquisitionIds'])) {
            foreach ($filter['weaponAcquisitionIds'] as $key => $weaponAcquisitionId) {
                if ($key) {
                    $query .= " OR weapon_weapon_acquisition.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                } else {
                    if ($hasValues) {
                        $query .= " AND (weapon_weapon_acquisition.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                    } else {
                        $query     .= " WHERE (weapon_weapon_acquisition.weapon_acquisition_id = :weaponAcquisitionId_" . $key;
                        $hasValues = true;
                    }
                }
            }

            $query     .= ")";
            $hasValues = true;
        }

        if ($type !== 'WeaponTwo' && $filter['weaponTwo'] !== 0) {
            if ($hasValues) {
                switch ($filter['weaponTwo']) {
                    case Weapon::FILTER_WEAPON_TWO_YES:
                        $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS:
                        $query .= " AND (weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) OR weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon))";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS:
                        $query .= " AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_NO:
                        $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                }
            } else {
                switch ($filter['weaponTwo']) {
                    case Weapon::FILTER_WEAPON_TWO_YES:
                        $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_YES_WITH_OTHERS:
                        $query .= " WHERE (weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) OR weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon))";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_NO_WITH_OTHERS:
                        $query .= " WHERE weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                    case Weapon::FILTER_WEAPON_TWO_NO:
                        $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon) AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_two_id) FROM weapon_weapon)";
                        break;
                }
                $hasValues = true;
            }
        }

        if ($type !== 'Evolution' && $filter['evolution'] !== 0) {
            if ($hasValues) {
                switch ($filter['evolution']) {
                    case Weapon::FILTER_EVOLUTION_YES:
                        $query .= " AND weapon.evolution = 1";
                        break;
                    case Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS:
                        $query .= " AND weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                        break;
                    case Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS:
                        $query .= " AND weapon.evolution = 0";
                        break;
                    case Weapon::FILTER_EVOLUTION_NO:
                        $query .= " AND weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                        break;
                }
            } else {
                switch ($filter['evolution']) {
                    case Weapon::FILTER_EVOLUTION_YES:
                        $query .= " WHERE weapon.evolution = 1";
                        break;
                    case Weapon::FILTER_EVOLUTION_YES_WITH_OTHERS:
                        $query .= " WHERE weapon.id NOT IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                        break;
                    case Weapon::FILTER_EVOLUTION_NO_WITH_OTHERS:
                        $query .= " WHERE weapon.evolution = 0";
                        break;
                    case Weapon::FILTER_EVOLUTION_NO:
                        $query .= " WHERE weapon.id IN (SELECT DISTINCT(weapon_weapon.weapon_one_id) FROM weapon_weapon LEFT JOIN weapon v ON weapon_weapon.weapon_two_id = v.id WHERE v.evolution = 1)";
                        break;
                }
                $hasValues = true;
            }
        }

        if ($type !== 'NotWeaponClassId' && !empty($filter['notWeaponClassIds'])) {
            foreach ($filter['notWeaponClassIds'] as $key => $notWeaponClassId) {
                if ($hasValues) {
                    $query .= " AND weapon_class.id <> :notWeaponClassId_" . $key;
                } else {
                    $query     .= " WHERE weapon_class.id <> :notWeaponClassId_" . $key;
                    $hasValues = true;
                }
            }
        }

        if ($type !== 'NotWeaponAcquisitionId' && !empty($filter['notWeaponAcquisitionIds'])) {
            foreach ($filter['notWeaponAcquisitionIds'] as $key => $notWeaponAcquisitionId) {
                if ($hasValues) {
                    $query .= " AND weapon_weapon_acquisition.weapon_acquisition_id <> :notWeaponAcquisitionId_" . $key;
                } else {
                    $query     .= " WHERE weapon_weapon_acquisition.weapon_acquisition_id <> :notWeaponAcquisitionId_" . $key;
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
        if ($filter['page'] > 1 && $this->getWeapons($filter, 'id') <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('weapon', $filter);
        }

        return $filter;
    }
}

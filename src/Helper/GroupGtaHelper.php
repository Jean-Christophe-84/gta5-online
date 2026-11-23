<?php

namespace App\Helper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GroupGtaHelper
{
    const array ACTIONS = ['VIEW', 'ADD', 'EDIT', 'DELETE'];

    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag,
        private RequestStack        $requestStack, private Security $security
    ) {
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('groupGta')) {
            return ['search' => '', 'page' => 1, 'order' => ['group_gta_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getGroupGta(int $groupGtaId): array|int
    {
        $query = "
            SELECT group_gta.id, group_gta.default, group_gta_locale.name, group_gta_locale.description
            FROM group_gta
            LEFT JOIN group_gta_locale ON group_gta.id = group_gta_locale.group_gta_id AND group_gta_locale.locale_id = :localeId
            WHERE group_gta.id = :groupGtaId
            GROUP BY group_gta.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('groupGtaId', $groupGtaId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @return array{actions: string[], bundles: array<string, array<string, array{id: int, active: bool}>>}
     * @throws DoctrineDBALException
     */
    public function getPermissions(?int $groupGtaId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT permission.id, permission.bundle, permission.attribute, COALESCE(permission_group_gta.active, 0) AS active
             FROM permission
             LEFT JOIN permission_group_gta ON permission_group_gta.permission_id = permission.id AND permission_group_gta.group_gta_id = :groupGtaId
             ORDER BY permission.bundle ASC, permission.id ASC",
            ['groupGtaId' => (int)$groupGtaId]
        );

        $actions = [];
        $bundles = [];

        foreach ($rows as $row) {
            $action                            = substr($row['attribute'], strlen($row['bundle']) + 1);
            $actions[$action]                  = true;
            $bundles[$row['bundle']][$action] = ['id' => (int)$row['id'], 'active' => (bool)$row['active']];
        }

        $actions = array_keys($actions);
        usort($actions, static function (string $a, string $b): int {
            $rankA = array_search($a, self::ACTIONS, true);
            $rankB = array_search($b, self::ACTIONS, true);

            return [$rankA === false, $rankA, $a] <=> [$rankB === false, $rankB, $b];
        });

        return ['actions' => $actions, 'bundles' => $bundles];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function savePermissions(int $groupGtaId, array $permissionIds): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "INSERT INTO permission_group_gta (group_gta_id, permission_id, active)
             SELECT :groupGtaId, permission.id, permission.id IN (:permissionIds) FROM permission
             ON DUPLICATE KEY UPDATE active = VALUES(active)",
            ['groupGtaId' => $groupGtaId, 'permissionIds' => $permissionIds ?: [0]],
            ['permissionIds' => ArrayParameterType::INTEGER]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getGroupGtas(array $filter, string $count = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count !== '') {
            $query = "SELECT COUNT(DISTINCT group_gta.id) AS count";
        } else {
            $query = "SELECT group_gta.id, group_gta_locale.name, group_gta_locale.description";
        }

        $query .= "
            FROM group_gta
            LEFT JOIN group_gta_locale ON group_gta.id = group_gta_locale.group_gta_id AND group_gta_locale.locale_id = :localeId
        ";

        foreach ($values as $key => $value) {
            if ($key) {
                $query .= "
                        AND (
                            group_gta_locale.name LIKE :search_" . $key . "
                            OR group_gta_locale.description LIKE :search_" . $key . "
                        )
                    ";
            } else {
                $query .= "
                        WHERE (
                            group_gta_locale.name LIKE :search_" . $key . "
                            OR group_gta_locale.description LIKE :search_" . $key . "
                        )
                    ";
            }
        }

        $query .= $count !== '' ? "" : " GROUP BY group_gta.id";

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

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
        return $this->configurationHelper->formatNumber($this->getGroupGtas($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getGroupGtas($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('groupGta', $filter);
        }

        return $filter;
    }
}

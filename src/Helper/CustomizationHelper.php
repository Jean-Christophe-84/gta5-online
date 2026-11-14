<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CustomizationHelper
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
    public function delete(int $customizationId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM customization_locale WHERE customization_id = :id",
                "DELETE FROM customization WHERE id = :id"
            ],
            $customizationId
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getCustomization(int $customizationId): array|int
    {
        $query = "
            SELECT customization_locale.customization_id AS id, customization_locale.name
            FROM customization_locale
            WHERE customization_locale.customization_id = :customizationId AND customization_locale.locale_id = :localeId
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('customizationId', $customizationId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getCustomizations(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT customization_locale.customization_id) AS count";
        } else {
            $query = "SELECT customization_locale.customization_id AS id, customization_locale.name";
        }

        $query .= " FROM customization_locale WHERE customization_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND customization_locale.name LIKE :search_" . $key;
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
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $customizationId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'weapons' => "SELECT COALESCE(weapon_locale.name, weapon.id) FROM weapon_customization INNER JOIN weapon ON weapon.id = weapon_customization.weapon_id LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId WHERE weapon_customization.customization_id = :id ORDER BY 1"
                ],
                $customizationId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('customization')) {
            return ['search' => '', 'page' => 1, 'order' => ['customization_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getCustomizations($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getCustomizations($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('customization', $filter);
        }

        return $filter;
    }
}

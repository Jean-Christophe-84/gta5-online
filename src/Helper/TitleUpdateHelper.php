<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class TitleUpdateHelper
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
    public function delete(int $titleUpdateId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM title_update WHERE id = :id"
            ],
            $titleUpdateId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $titleUpdateId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'businesses' => "SELECT COALESCE(business_locale.name, business.id) FROM business LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId WHERE business.title_update_id = :id ORDER BY 1",
                    'properties' => "SELECT property.name FROM property WHERE property.title_update_id = :id ORDER BY property.name",
                    'vehicles' => "SELECT vehicle.name FROM vehicle WHERE vehicle.title_update_id = :id ORDER BY vehicle.name",
                    'weapons' => "SELECT COALESCE(weapon_locale.name, weapon.id) FROM weapon LEFT JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = :localeId WHERE weapon.title_update_id = :id ORDER BY 1"
                ],
                $titleUpdateId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('titleUpdate')) {
            return ['search' => '', 'page' => 1, 'order' => ['title_update-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTitleUpdate(int $titleUpdateId): array|int
    {
        $query = "
            SELECT title_update.id, title_update.name, title_update.release_date AS releaseDate
            FROM title_update
            WHERE title_update.id = :titleUpdateId
            GROUP BY title_update.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('titleUpdateId', $titleUpdateId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTitleUpdates(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT title_update.id) AS count";
        } else {
            $query = "SELECT title_update.id, title_update.name, title_update.release_date AS releaseDate";
        }

        $query .= " FROM title_update";

        foreach ($values as $key => $value) {
            $query .= $key ? " AND title_update.name LIKE :search_" . $key : " WHERE title_update.name LIKE :search_" . $key;
        }

        $query .= $count ? "" : " GROUP BY title_update.id";

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
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getTitleUpdates($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getTitleUpdates($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('titleUpdate', $filter);
        }

        return $filter;
    }
}

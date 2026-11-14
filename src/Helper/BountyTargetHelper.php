<?php

namespace App\Helper;

use App\Entity\BountyTarget;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class BountyTargetHelper
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
    public function delete(int $bountyTargetId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM bounty_target_user WHERE bounty_target_id = :id",
                "DELETE FROM bounty_target WHERE id = :id"
            ],
            $bountyTargetId
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBountyTarget(int $bountyTargetId): array|false
    {
        $query = "
            SELECT bounty_target.id, bounty_target.name, bounty_target.most_wanted AS mostWanted,
                   bounty_mission.id AS bountyMissionId, bounty_mission_locale.name AS bountyMissionName, bounty_target_user.done
            FROM bounty_target
            LEFT JOIN bounty_mission ON bounty_mission.id = bounty_target.bounty_mission_id
            LEFT JOIN bounty_mission_locale
                ON bounty_mission_locale.bounty_mission_id = bounty_mission.id AND bounty_mission_locale.locale_id = :localeId
            LEFT JOIN bounty_target_user ON bounty_target_user.bounty_target_id = bounty_target.id AND bounty_target_user.user_id = :userId
            WHERE bounty_target.id = :bountyTargetId
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('bountyTargetId', $bountyTargetId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('userId', $this->security->getUser()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBountyTargets(array $filter, string $type = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($type === 'count') {
            $query = "SELECT COUNT(DISTINCT bounty_target.id) AS count";
        } elseif ($type === 'summary') {
            $query = "
                SELECT COUNT(bounty_target.id) AS total, COALESCE(SUM(bounty_target_user.done = 1), 0) AS owned,
                       COALESCE(SUM(IF(bounty_target_user.done = 1, " . $this->getPriceExpression(false) . ", 0)), 0) AS ownedPriceMin,
                       COALESCE(SUM(IF(bounty_target_user.done = 1, " . $this->getPriceExpression(true) . ", 0)), 0) AS ownedPriceMax,
                       COALESCE(SUM(COALESCE(bounty_target_user.done, 0) = 0), 0) AS toBuy,
                       COALESCE(SUM(IF(COALESCE(bounty_target_user.done, 0) = 0, " . $this->getPriceExpression(false) . ", 0)), 0) AS toBuyPriceMin,
                       COALESCE(SUM(IF(COALESCE(bounty_target_user.done, 0) = 0, " . $this->getPriceExpression(true) . ", 0)), 0) AS toBuyPriceMax,
                       COALESCE(SUM(bounty_target.most_wanted = 1), 0) AS mostWanted,
                       COALESCE(SUM(bounty_target.most_wanted = 1 AND bounty_target_user.done = 1), 0) AS mostWantedOwned
            ";
        } else {
            $query = "
                SELECT bounty_target.id, bounty_target.name, bounty_target.most_wanted AS mostWanted,
                       " . $this->getPriceExpression(false) . " AS priceMin, " . $this->getPriceExpression(true) . " AS priceMax,
                       bounty_mission_locale.name AS bountyMissionName, bounty_target_user.done
            ";
        }

        $query .= "
            FROM bounty_target
            LEFT JOIN bounty_mission ON bounty_mission.id = bounty_target.bounty_mission_id
            LEFT JOIN bounty_mission_locale
                ON bounty_mission_locale.bounty_mission_id = bounty_mission.id AND bounty_mission_locale.locale_id = :localeId
            LEFT JOIN bounty_target_user ON bounty_target_user.bounty_target_id = bounty_target.id AND bounty_target_user.user_id = :userId
        ";

        foreach ($values as $key => $value) {
            $query .= ($key ? " AND" : " WHERE") . " (bounty_target.name LIKE :search_" . $key . " OR bounty_mission_locale.name LIKE :search_" . $key . ")";
        }

        if ($type === '') {
            if (!empty($filter['order'])) {
                $query .= $this->configurationHelper->getOrderBy($query, $filter['order'], ['bounty_target-price' => 'priceMin']);
            }

            $query .= " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));
        }

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('userId', $this->security->getUser()->id);
        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($type === 'count') {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        if ($type === 'summary') {
            return $statement->executeQuery()->fetchAssociative();
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $bountyTargetId): array
    {
        $warnings = [];

        foreach ([
            'deletion_removed_from_users' => "SELECT COUNT(*) FROM bounty_target_user WHERE bounty_target_id = :id AND done = 1"
        ] as $key => $query) {
            if ($count = (int)$this->entityManager->getConnection()->fetchOne($query, ['id' => $bountyTargetId])) {
                $warnings[] = $this->configurationHelper->getTranslateLocale($key, ['count' => $count]);
            }
        }

        return [
            'blockers' => [],
            'warnings' => $warnings
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('bountyTarget')) {
            return ['search' => '', 'page' => 1, 'order' => ['bounty_target-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getParameters(array $filter): array
    {
        return [
            'bountyTargets' => $this->getBountyTargets($filter),
            'filter'        => $filter,
            'summary'       => $this->security->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_DONE) ? $this->getBountyTargets($filter, 'summary') : null,
            'totalNames'    => $this->getTotalNames()
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getBountyTargets($this->getFilter($defaultFilter), 'count'));
    }

    /**
     * Prime (vivante) d'une cible : elle ne dépend que du drapeau most_wanted. Les constantes sont des entiers, insérés tels quels dans la requête
     */
    private function getPriceExpression(bool $max): string
    {
        if ($max) {
            return "IF(bounty_target.most_wanted = 1, " . BountyTarget::PRICE_MOST_WANTED_MAX . ", " . BountyTarget::PRICE_MAX . ")";
        }

        return "IF(bounty_target.most_wanted = 1, " . BountyTarget::PRICE_MOST_WANTED_MIN . ", " . BountyTarget::PRICE_MIN . ")";
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getBountyTargets($filter, 'count') <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('bountyTarget', $filter);
        }

        return $filter;
    }
}

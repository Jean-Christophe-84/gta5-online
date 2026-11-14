<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class BusinessGoodHelper
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
    public function delete(int $businessGoodId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM business_good_locale WHERE business_good_id = :id",
                "DELETE FROM business_good WHERE id = :id"
            ],
            $businessGoodId
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusinessGood(int $businessGoodId): array|int
    {
        $query = "
            SELECT business_good.id AS id, business.id AS businessId, business_locale.name AS businessName,
                   business_good_locale.additional_informations AS additionalInformations, business_good.stock_dealer AS stockDealer,
                   business_good.stock_dealer_total AS stockDealerTotal, business_good.cost_of_restock AS costOfRestock,
                   business_good.replenishment_rate AS replenishmentRate,
                   business_good.replenishment_rate_with_upgraded_equipment AS replenishmentRateWithUpgradedEquipment,
                   business_good.replenishment_rate_with_upgraded_staff AS replenishmentRateWithUpgradedStaff,
                   business_good.replenishment_rate_with_upgraded_equipment_and_staff AS replenishmentRateWithUpgradedEquipmentAndStaff,
                   business_good.sale AS sale, business_good.sale_with_upgraded_equipment AS saleWithUpgradedEquipment,
                   business_good.sale_with_upgraded_staff AS saleWithUpgradedStaff,
                   business_good.sale_with_upgraded_equipment_and_staff AS saleWithUpgradedEquipmentAndStaff,
                   business_good.time_for_restock AS timeForRestock,
                   business_good.time_for_restock_with_upgraded_equipment AS timeForRestockWithUpgradedEquipment,
                   business_good.time_for_restock_with_upgraded_staff AS timeForRestockWithUpgradedStaff,
                   business_good.time_for_restock_with_upgraded_equipment_and_staff AS timeForRestockWithUpgradedEquipmentAndStaff
            FROM business_good
            LEFT JOIN business_good_locale ON business_good_locale.business_good_id = business_good.id AND business_good_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = business_good.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
            WHERE business_good.id = :businessGoodId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('businessGoodId', $businessGoodId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getBusinessGoods(array $filter, string $count = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count !== '') {
            $query = "SELECT COUNT(DISTINCT business_good.id) AS count";
        } else {
            $query = "
                SELECT business_good.id AS id, business.id AS businessId, business_locale.name AS businessName,
                business_good_locale.additional_informations AS additionalInformations, business_good.stock_dealer AS stockDealer,
                business_good.stock_dealer_total AS stockDealerTotal, business_good.cost_of_restock AS costOfRestock,
                business_good.replenishment_rate AS replenishmentRate,
                business_good.replenishment_rate_with_upgraded_equipment AS replenishmentRateWithUpgradedEquipment,
                business_good.replenishment_rate_with_upgraded_staff AS replenishmentRateWithUpgradedStaff,
                business_good.replenishment_rate_with_upgraded_equipment_and_staff AS replenishmentRateWithUpgradedEquipmentAndStaff,
                business_good.sale AS sale, business_good.sale_with_upgraded_equipment AS saleWithUpgradedEquipment,
                business_good.sale_with_upgraded_staff AS saleWithUpgradedStaff,
                business_good.sale_with_upgraded_equipment_and_staff AS saleWithUpgradedEquipmentAndStaff,
                business_good.time_for_restock AS timeForRestock, business_good.time_for_restock_with_upgraded_equipment AS timeForRestockWithUpgradedEquipment,
                business_good.time_for_restock_with_upgraded_staff AS timeForRestockWithUpgradedStaff,
                business_good.time_for_restock_with_upgraded_equipment_and_staff AS timeForRestockWithUpgradedEquipmentAndStaff
            ";
        }

        $query .= "
            FROM business_good
            LEFT JOIN business_good_locale ON business_good_locale.business_good_id = business_good.id AND business_good_locale.locale_id = :localeId
            LEFT JOIN business ON business.id = business_good.business_id
            LEFT JOIN business_locale ON business_locale.business_id = business.id AND business_locale.locale_id = :localeId
        ";

        foreach ($values as $key => $value) {
            if ($key) {
                $query .= " AND (business_locale.name LIKE :search_" . $key . " OR business_good_locale.additional_informations LIKE :search_" . $key . ")";
            } else {
                $query .= " WHERE (business_locale.name LIKE :search_" . $key . " OR business_good_locale.additional_informations LIKE :search_" . $key . ")";
            }
        }

        $query .= $count !== '' ? "" : " GROUP BY business_good.id";

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($count !== '') {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $businessGoodId): array
    {
        return [
            'blockers' => [],
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('businessGood')) {
            return ['search' => '', 'page' => 1, 'order' => ['business_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getBusinessGoods($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getBusinessGoods($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('businessGood', $filter);
        }

        return $filter;
    }
}

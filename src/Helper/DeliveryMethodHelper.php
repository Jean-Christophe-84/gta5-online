<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class DeliveryMethodHelper
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
    public function delete(int $deliveryMethodId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM delivery_method_locale WHERE delivery_method_id = :id",
                "DELETE FROM delivery_method WHERE id = :id"
            ],
            $deliveryMethodId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $deliveryMethodId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'vehicles' => "SELECT vehicle.name FROM vehicle_delivery_method INNER JOIN vehicle ON vehicle.id = vehicle_delivery_method.vehicle_id WHERE vehicle_delivery_method.delivery_method_id = :id ORDER BY vehicle.name"
                ],
                $deliveryMethodId
            ),
            'warnings' => []
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getDeliveryMethod(int $deliveryMethodId): array|int
    {
        $query = "
            SELECT delivery_method_locale.delivery_method_id AS id, delivery_method_locale.name
            FROM delivery_method_locale
            WHERE delivery_method_locale.delivery_method_id = :deliveryMethodId AND delivery_method_locale.locale_id = :localeId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('deliveryMethodId', $deliveryMethodId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getDeliveryMethods(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT delivery_method_locale.delivery_method_id) AS count";
        } else {
            $query = "SELECT delivery_method_locale.delivery_method_id AS id, delivery_method_locale.name";
        }

        $query .= " FROM delivery_method_locale WHERE delivery_method_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND delivery_method_locale.name LIKE :search_" . $key;
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

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('deliveryMethod')) {
            return ['search' => '', 'page' => 1, 'order' => ['delivery_method_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getDeliveryMethods($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getDeliveryMethods($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('deliveryMethod', $filter);
        }

        return $filter;
    }
}

<?php

namespace App\Helper;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class PropertyAcquisitionHelper
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
    public function delete(int $propertyAcquisitionId): void
    {
        $this->configurationHelper->deleteRows(
            [
                "DELETE FROM property_acquisition_locale WHERE property_acquisition_id = :id",
                "DELETE FROM property_acquisition WHERE id = :id"
            ],
            $propertyAcquisitionId
        );
    }

    /**
     * Éléments liés qui bloquent la suppression, et ce qu'elle retirera aux utilisateurs
     *
     * @throws DoctrineDBALException
     */
    public function getDeletion(int $propertyAcquisitionId): array
    {
        return [
            'blockers' => $this->configurationHelper->getDeletionBlockers(
                [
                    'properties' => "SELECT property.name FROM property_property_acquisition INNER JOIN property ON property.id = property_property_acquisition.property_id WHERE property_property_acquisition.property_acquisition_id = :id ORDER BY property.name"
                ],
                $propertyAcquisitionId
            ),
            'warnings' => []
        ];
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('propertyAcquisition')) {
            return ['search' => '', 'page' => 1, 'order' => ['property_acquisition_locale-name' => 'ASC']];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPropertyAcquisition(int $propertyAcquisitionId): array|int
    {
        $query = "
            SELECT property_acquisition_locale.property_acquisition_id AS id, property_acquisition_locale.name, property_acquisition.promotion
            FROM property_acquisition_locale
            INNER JOIN property_acquisition ON property_acquisition.id = property_acquisition_locale.property_acquisition_id
            WHERE property_acquisition_locale.property_acquisition_id = :propertyAcquisitionId AND property_acquisition_locale.locale_id = :localeId
            LIMIT 1
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('propertyAcquisitionId', $propertyAcquisitionId);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPropertyAcquisitions(array $filter, bool $count = false): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count) {
            $query = "SELECT COUNT(DISTINCT property_acquisition_locale.property_acquisition_id) AS count";
        } else {
            $query = "SELECT property_acquisition_locale.property_acquisition_id AS id, property_acquisition_locale.name";
        }

        $query .= " FROM property_acquisition_locale WHERE property_acquisition_locale.locale_id = :localeId";

        foreach ($values as $key => $value) {
            $query .= " AND property_acquisition_locale.name LIKE :search_" . $key;
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
        return $this->configurationHelper->formatNumber($this->getPropertyAcquisitions($this->getFilter($defaultFilter), true));
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getPropertyAcquisitions($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('propertyAcquisition', $filter);
        }

        return $filter;
    }
}

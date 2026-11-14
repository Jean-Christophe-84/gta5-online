<?php

namespace App\Controller\Property;

use App\Entity\Property;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UpdateController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/property/update', name: 'update_property', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function update(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        PropertyHelper      $propertyHelper, Request $request
    ): Response {
        if (!$this->isGranted(Property::PERMISSION_PROPERTY_OWN)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        /** @var User $user */
        $user     = $this->getUser();
        $property = $propertyHelper->getProperty((int)$request->request->get('propertyId'));

        if (!$property) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if ($message = $propertyHelper->getBusinessMaximumMessage(
            (int)$request->request->get('propertyId'), $property['businessId'] ?? null,
            (bool)$request->request->get('owned'), (bool)$request->request->get('wanted')
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if ($message = $propertyHelper->getParentPropertyMessage((int)$request->request->get('propertyId'), (bool)$request->request->get('owned'))) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            $sql        = "
                UPDATE property
                LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
                SET property_user.owned = :owned, property_user.wanted = :wanted, property_user.updated_at = :updatedAt, property_user.updated_by_id = :userId
                WHERE property.id = :propertyId
            ";
            $parameters = [
                'owned'      => $request->request->get('owned'),
                'updatedAt'  => date('Y-m-d H:i:s'),
                'userId'     => $user->id,
                'wanted'     => $request->request->get('wanted'),
                'propertyId' => $request->request->get('propertyId')
            ];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            if ($request->request->get('owned') === "0") {
                $sql        = "
                    UPDATE property
                    LEFT JOIN property_user ON property.id = property_user.property_id AND property_user.user_id = :userId
                    SET property_user.owned = :owned, property_user.updated_at = :updatedAt, property_user.updated_by_id = :userId
                    WHERE property.id IN (SELECT property_property.property_two_id FROM property_property WHERE property_property.property_one_id = :propertyId)
                    AND property.id NOT IN (
                        SELECT owned_parent.id
                        FROM (
                            SELECT parent_property_property.property_two_id AS id
                            FROM property_property parent_property_property
                            INNER JOIN property_user parent_property_user
                                ON parent_property_user.property_id = parent_property_property.property_one_id AND parent_property_user.user_id = :userId
                                AND parent_property_user.owned = 1
                        ) AS owned_parent
                    )
                ";
                $parameters = [
                    'owned'      => $request->request->get('owned'),
                    'updatedAt'  => date('Y-m-d H:i:s'),
                    'userId'     => $user->id,
                    'propertyId' => $request->request->get('propertyId')
                ];

                $entityManager->getConnection()->executeStatement($sql, $parameters);
            }

            $parameters = $propertyHelper->getParameters($propertyHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'     => $this->renderView('Property/_partial/filter.html.twig', $parameters),
                    'filterTwo'  => $this->renderView('Property/_partial/filter_two.html.twig', $parameters),
                    'message'    => ucfirst(
                        $configurationHelper->getTranslateLocale('success_property_list', ['name' => $property['name']])
                    ),
                    'properties' => $parameters['properties'],
                    'success'    => true,
                    'summary'    => $this->renderView('_partial/summary.html.twig', ['entity' => 'properties', 'summary' => $parameters['summary']]),
                    'template'   => $this->renderView('Property/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale('error_property_list', ['name' => $property['name']])
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

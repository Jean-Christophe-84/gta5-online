<?php

namespace App\Controller\PropertyType;

use App\Entity\PropertyType;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyTypeHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/property-type/delete', name: 'delete_property-type', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, PropertyTypeHelper $propertyTypeHelper, Request $request
    ): Response {
        if (!$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $propertyTypeId = (int)$request->request->get('propertyTypeId');

        if (!$propertyType = $propertyTypeHelper->getPropertyType($propertyTypeId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($propertyTypeHelper->getDeletion($propertyTypeId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $propertyTypeHelper->delete($propertyTypeId);

            $filter = $propertyTypeHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'        => $this->renderView(
                        'PropertyType/_partial/filter.html.twig',
                        [
                            'filter'                 => $filter,
                            'filterStorageLocations' => $propertyTypeHelper->getFilterStorageLocations($filter)
                        ]
                    ),
                    'message'       => ucfirst($configurationHelper->getTranslateLocale('success_property_type_delete', ['name' => $propertyType['name']])),
                    'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                    'success'       => true,
                    'template'      => $this->renderView(
                        'PropertyType/_partial/template.html.twig',
                        [
                            'filter'        => $filter,
                            'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                            'totalNames'    => $propertyTypeHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_property_type_delete', ['name' => $propertyType['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

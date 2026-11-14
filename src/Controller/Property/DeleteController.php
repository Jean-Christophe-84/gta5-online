<?php

namespace App\Controller\Property;

use App\Entity\Property;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
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
    #[Route(path: '/property/delete', name: 'delete_property', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, PropertyHelper $propertyHelper, Request $request
    ): Response {
        if (!$this->isGranted(Property::PERMISSION_PROPERTY_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $propertyId = (int)$request->request->get('propertyId');

        if (!$property = $propertyHelper->getProperty($propertyId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($propertyHelper->getDeletion($propertyId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $propertyHelper->delete($propertyId);

            $parameters = $propertyHelper->getParameters($propertyHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'     => $this->renderView('Property/_partial/filter.html.twig', $parameters),
                    'filterTwo'  => $this->renderView('Property/_partial/filter_two.html.twig', $parameters),
                    'message'    => ucfirst($configurationHelper->getTranslateLocale('success_property_delete', ['name' => $property['name']])),
                    'properties' => $parameters['properties'],
                    'success'    => true,
                    'summary'    => $this->renderView('_partial/summary.html.twig', ['entity' => 'properties', 'summary' => $parameters['summary']]),
                    'template'   => $this->renderView('Property/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_property_delete', ['name' => $property['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

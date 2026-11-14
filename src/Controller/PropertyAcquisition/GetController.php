<?php

namespace App\Controller\PropertyAcquisition;

use App\Entity\PropertyAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyAcquisitionHelper;
use App\Service\DeletionResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/property-acquisition/get', name: 'get_property-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getPropertyAcquisition(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, PropertyAcquisitionHelper $propertyAcquisitionHelper,
        Request             $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('propertyAcquisitionId')) {
            if (!$this->isGranted($delete ? PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_DELETE : PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $propertyAcquisition = $propertyAcquisitionHelper->getPropertyAcquisition($request->query->get('propertyAcquisitionId'));
        } else {
            if ($delete || !$this->isGranted(PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $propertyAcquisition = null;
        }

        $template = $this->renderView('PropertyAcquisition/_partial/body.html.twig', ['propertyAcquisition' => $propertyAcquisition]);

        if ($delete) {
            return $deletionResponder->json($template, $propertyAcquisitionHelper->getDeletion((int)$request->query->get('propertyAcquisitionId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

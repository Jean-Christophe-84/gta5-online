<?php

namespace App\Controller\VehicleAcquisition;

use App\Entity\VehicleAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleAcquisitionHelper;
use App\Service\DeletionResponder;
use Exception;
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
     * @throws Exception
     */
    #[Route(path: '/vehicle-acquisition/get', name: 'get_vehicle-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getVehicleAcquisition(
        ConfigurationHelper      $configurationHelper, DeletionResponder $deletionResponder, Request $request,
        VehicleAcquisitionHelper $vehicleAcquisitionHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('vehicleAcquisitionId')) {
            if (!$this->isGranted($delete ? VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_DELETE : VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicleAcquisition = $vehicleAcquisitionHelper->getVehicleAcquisition($request->query->get('vehicleAcquisitionId'));
        } else {
            if ($delete || !$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicleAcquisition = null;
        }

        $template = $this->renderView('VehicleAcquisition/_partial/body.html.twig', ['vehicleAcquisition' => $vehicleAcquisition]);

        if ($delete) {
            return $deletionResponder->json($template, $vehicleAcquisitionHelper->getDeletion((int)$request->query->get('vehicleAcquisitionId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

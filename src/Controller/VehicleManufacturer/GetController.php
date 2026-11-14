<?php

namespace App\Controller\VehicleManufacturer;

use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleManufacturerHelper;
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
    #[Route(path: '/vehicle-manufacturer/get', name: 'get_vehicle-manufacturer')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getVehicleManufacturer(
        ConfigurationHelper       $configurationHelper, DeletionResponder $deletionResponder, Request $request,
        VehicleManufacturerHelper $vehicleManufacturerHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('vehicleManufacturerId')) {
            if (!$this->isGranted($delete ? VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_DELETE : VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicleManufacturer = $vehicleManufacturerHelper->getVehicleManufacturer($request->query->get('vehicleManufacturerId'));
        } else {
            if ($delete || !$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicleManufacturer = null;
        }

        $template = $this->renderView('VehicleManufacturer/_partial/body.html.twig', ['vehicleManufacturer' => $vehicleManufacturer]);

        if ($delete) {
            return $deletionResponder->json($template, $vehicleManufacturerHelper->getDeletion((int)$request->query->get('vehicleManufacturerId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

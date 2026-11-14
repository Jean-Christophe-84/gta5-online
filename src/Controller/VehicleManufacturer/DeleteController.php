<?php

namespace App\Controller\VehicleManufacturer;

use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleManufacturerHelper;
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
    #[Route(path: '/vehicle-manufacturer/delete', name: 'delete_vehicle-manufacturer', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, VehicleManufacturerHelper $vehicleManufacturerHelper
    ): Response {
        if (!$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $vehicleManufacturerId = (int)$request->request->get('vehicleManufacturerId');

        if (!$vehicleManufacturer = $vehicleManufacturerHelper->getVehicleManufacturer($vehicleManufacturerId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($vehicleManufacturerHelper->getDeletion($vehicleManufacturerId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $vehicleManufacturerHelper->delete($vehicleManufacturerId);

            $filter = $vehicleManufacturerHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'               => $this->renderView('VehicleManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'              => ucfirst($configurationHelper->getTranslateLocale('success_vehicle_manufacturer_delete', ['name' => $vehicleManufacturer['name']])),
                    'success'              => true,
                    'template'             => $this->renderView(
                        'VehicleManufacturer/_partial/template.html.twig',
                        [
                            'filter'               => $filter,
                            'totalNames'           => $vehicleManufacturerHelper->getTotalNames(),
                            'vehicleManufacturers' => $vehicleManufacturerHelper->getVehicleManufacturers($filter)
                        ]
                    ),
                    'vehicleManufacturers' => $vehicleManufacturerHelper->getVehicleManufacturers($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_vehicle_manufacturer_delete', ['name' => $vehicleManufacturer['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

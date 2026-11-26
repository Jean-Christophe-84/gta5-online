<?php

namespace App\Controller\VehicleAcquisition;

use App\Entity\VehicleAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleAcquisitionHelper;
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
    #[Route(path: '/vehicle-acquisition/delete', name: 'delete_vehicle-acquisition', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, VehicleAcquisitionHelper $vehicleAcquisitionHelper
    ): Response {
        if (!$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $vehicleAcquisitionId = (int)$request->request->get('vehicleAcquisitionId');

        if (!$vehicleAcquisition = $vehicleAcquisitionHelper->getVehicleAcquisition($vehicleAcquisitionId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($vehicleAcquisitionHelper->getDeletion($vehicleAcquisitionId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $vehicleAcquisitionHelper->delete($vehicleAcquisitionId);

            $filter = $vehicleAcquisitionHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'              => $this->renderView('VehicleAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'             => ucfirst($configurationHelper->getTranslateLocale('success_vehicle_acquisition_delete', ['name' => $vehicleAcquisition['name']])),
                    'success'             => true,
                    'template'            => $this->renderView(
                        'VehicleAcquisition/_partial/template.html.twig',
                        [
                            'filter'              => $filter,
                            'totalNames'          => $vehicleAcquisitionHelper->getTotalNames(),
                            'vehicleAcquisitions' => $vehicleAcquisitionHelper->getVehicleAcquisitions($filter)
                        ]
                    ),
                    'vehicleAcquisitions' => $vehicleAcquisitionHelper->getVehicleAcquisitions($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_vehicle_acquisition_delete', ['name' => $vehicleAcquisition['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

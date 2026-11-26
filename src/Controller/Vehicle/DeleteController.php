<?php

namespace App\Controller\Vehicle;

use App\Entity\Vehicle;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
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
    #[Route(path: '/vehicle/delete', name: 'delete_vehicle', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, VehicleHelper $vehicleHelper
    ): Response {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $vehicleId = (int)$request->request->get('vehicleId');

        if (!$vehicle = $vehicleHelper->getVehicle($vehicleId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($vehicleHelper->getDeletion($vehicleId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $vehicleHelper->delete($vehicleId);

            $parameters = $vehicleHelper->getParameters($vehicleHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Vehicle/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Vehicle/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst($configurationHelper->getTranslateLocale('success_vehicle_delete', ['name' => $vehicle['name']])),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'vehicles', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Vehicle/_partial/template.html.twig', $parameters),
                    'vehicles'  => $parameters['vehicles']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_vehicle_delete', ['name' => $vehicle['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

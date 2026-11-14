<?php

namespace App\Controller\Vehicle;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
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
     * @throws DoctrineDBALException
     */
    #[Route(path: '/vehicle/update', name: 'update_vehicle', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function update(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        VehicleHelper       $vehicleHelper
    ): Response {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_OWN)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        /** @var User $user */
        $user    = $this->getUser();
        $vehicle = $vehicleHelper->getVehicle((int)$request->request->get('vehicleId'));

        if (!$vehicle) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        try {
            $sql        = "
                UPDATE vehicle
                LEFT JOIN vehicle_user ON vehicle.id = vehicle_user.vehicle_id AND vehicle_user.user_id = :userId
                SET vehicle_user.owned = :owned, vehicle_user.updated_at = :updatedAt, vehicle_user.updated_by_id = :userId
            ";
            $parameters = [
                'owned'     => $request->request->get('owned'),
                'updatedAt' => date('Y-m-d H:i:s'),
                'userId'    => $user->id,
                'vehicleId' => $request->request->get('vehicleId')
            ];

            if ($request->request->get('owned') !== "0") {
                if ($request->request->get('propertyId') !== null && $request->request->get('propertyId') !== "") {
                    $sql                      .= ", vehicle_user.property_id = :propertyId";
                    $parameters['propertyId'] = $request->request->get('propertyId');
                }
            } else {
                $sql .= ", vehicle_user.property_id = NULL";
            }

            if ($request->request->get('wantedPropertyId') !== null && $request->request->get('wantedPropertyId') !== "") {
                $sql                                 .= ", vehicle_user.wanted_property_id = :wantedPropertyId";
                $parameters['wantedPropertyId'] = $request->request->get('wantedPropertyId');
            } else {
                $sql .= ", vehicle_user.wanted_property_id = NULL";
            }

            $sql .= " WHERE vehicle.id = :vehicleId";

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            if ($request->request->get('owned') && $request->request->get('owned') !== "0") {
                $vehicleHelper->releaseLinkedEvolutions((int)$request->request->get('vehicleId'));
            }

            $parameters = $vehicleHelper->getParameters($vehicleHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Vehicle/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Vehicle/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst(
                        $configurationHelper->getTranslateLocale('success_vehicle_list', ['name' => $vehicle['name']])
                    ),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'vehicles', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Vehicle/_partial/template.html.twig', $parameters),
                    'vehicles'  => $parameters['vehicles']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale('error_vehicle_list', ['name' => $vehicle['name']])
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

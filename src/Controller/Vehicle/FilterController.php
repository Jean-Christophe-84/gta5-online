<?php

namespace App\Controller\Vehicle;

use App\Entity\Vehicle;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FilterController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/vehicle/filter', name: 'filter_vehicle')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, VehicleHelper $vehicleHelper): Response
    {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['vehicleManufacturerId']    = (int)$request->query->get('vehicleManufacturerId');
        $filter['titleUpdateId']            = (int)$request->query->get('titleUpdateId');
        $filter['owned']                    = $request->query->get('owned') !== "null" ? (bool)$request->query->get('owned') : null;
        $filter['propertyId']               = (int)$request->query->get('propertyId');
        $filter['wantedPropertyId']         = (int)$request->query->get('wantedPropertyId');
        $filter['seats']                    = (int)$request->query->get('seats');
        $filter['vehicleClassIds']          = $request->query->all('vehicleClassIds');
        $filter['vehicleAcquisitionIds']    = $request->query->all('vehicleAcquisitionIds');
        $filter['storageLocationIds']       = $request->query->all('storageLocationIds');
        $filter['search']                   = (string)$request->query->get('search');
        $filter['vehicleTwo']               = (int)$request->query->get('vehicleTwo');
        $filter['optimized']                = $request->query->get('optimized') !== "null" ? (bool)$request->query->get('optimized') : null;
        $filter['evolution']                = (int)$request->query->get('evolution');
        $filter['wantedProperty']           =
            $request->query->get('wantedProperty') !== "null" ? (bool)$request->query->get('wantedProperty') : null;
        $filter['notVehicleClassIds']       = $request->query->all('notVehicleClassIds');
        $filter['notVehicleAcquisitionIds'] = $request->query->all('notVehicleAcquisitionIds');
        $filter['notStorageLocationIds']    = $request->query->all('notStorageLocationIds');
        $filter['page']                     = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $vehicleHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('vehicle', $filter);

        $parameters = $vehicleHelper->getParameters($vehicleHelper->getFilter());

        return new JsonResponse(
            [
                'filter'    => $this->renderView('Vehicle/_partial/filter.html.twig', $parameters),
                'filterTwo' => $this->renderView('Vehicle/_partial/filter_two.html.twig', $parameters),
                'success'   => true,
                'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'vehicles', 'summary' => $parameters['summary']]),
                'template'  => $this->renderView('Vehicle/_partial/template.html.twig', $parameters),
                'vehicles'  => $parameters['vehicles']
            ]
        );
    }
}

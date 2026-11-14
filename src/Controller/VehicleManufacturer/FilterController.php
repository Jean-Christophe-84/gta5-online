<?php

namespace App\Controller\VehicleManufacturer;

use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleManufacturerHelper;
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
    #[Route(path: '/vehicle-manufacturer/filter', name: 'filter_vehicle-manufacturer')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, VehicleManufacturerHelper $vehicleManufacturerHelper
    ): Response {
        if (!$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $vehicleManufacturerHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('vehicleManufacturer', $filter);

        $filter = $vehicleManufacturerHelper->getFilter();

        return new JsonResponse(
            [
                'filter'               => $this->renderView('VehicleManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
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
    }
}

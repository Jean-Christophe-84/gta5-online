<?php

namespace App\Controller\VehicleAcquisition;

use App\Entity\VehicleAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleAcquisitionHelper;
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
    #[Route(path: '/vehicle-acquisition/filter', name: 'filter_vehicle-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, VehicleAcquisitionHelper $vehicleAcquisitionHelper
    ): Response {
        if (!$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $vehicleAcquisitionHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('vehicleAcquisition', $filter);

        $filter = $vehicleAcquisitionHelper->getFilter();

        return new JsonResponse(
            [
                'filter'              => $this->renderView('VehicleAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
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
    }
}

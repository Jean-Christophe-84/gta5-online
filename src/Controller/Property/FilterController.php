<?php

namespace App\Controller\Property;

use App\Entity\Property;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
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
    #[Route(path: '/property/filter', name: 'filter_property')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(ConfigurationHelper $configurationHelper, PropertyHelper $propertyHelper, Request $request, RequestStack $requestStack): Response
    {
        if (!$this->isGranted(Property::PERMISSION_PROPERTY_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['owned']                  = $request->query->get('owned') !== "null" ? (bool)$request->query->get('owned') : null;
        $filter['wanted']                 = $request->query->get('wanted') !== "null" ? (bool)$request->query->get('wanted') : null;
        $filter['propertyTypeId']         = (int)$request->query->get('propertyTypeId');
        $filter['businessId']             = (int)$request->query->get('businessId');
        $filter['propertyAreaId']         = (int)$request->query->get('propertyAreaId');
        $filter['propertyLocationId']     = (int)$request->query->get('propertyLocationId');
        $filter['titleUpdateId']          = (int)$request->query->get('titleUpdateId');
        $filter['capacityVehicles']       = $request->query->get('capacityVehicles') !== "null" ? (int)$request->query->get('capacityVehicles') : null;
        $filter['capacityBicycles']       = $request->query->get('capacityBicycles') !== "null" ? (int)$request->query->get('capacityBicycles') : null;
        $filter['propertyAcquisitionIds'] = $request->query->all('propertyAcquisitionIds');
        $filter['search']                 = (string)$request->query->get('search');
        $filter['page']                   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $propertyHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('property', $filter);

        $parameters = $propertyHelper->getParameters($propertyHelper->getFilter());

        return new JsonResponse(
            [
                'filter'     => $this->renderView('Property/_partial/filter.html.twig', $parameters),
                'filterTwo'  => $this->renderView('Property/_partial/filter_two.html.twig', $parameters),
                'properties' => $parameters['properties'],
                'success'    => true,
                'summary'    => $this->renderView('_partial/summary.html.twig', ['entity' => 'properties', 'summary' => $parameters['summary']]),
                'template'   => $this->renderView('Property/_partial/template.html.twig', $parameters)
            ]
        );
    }
}

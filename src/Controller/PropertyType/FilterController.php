<?php

namespace App\Controller\PropertyType;

use App\Entity\PropertyType;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyTypeHelper;
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
    #[Route(path: '/property-type/filter', name: 'filter_property-type')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, PropertyTypeHelper $propertyTypeHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['storageLocationIds'] = $request->query->all('storageLocationIds');
        $filter['search']             = (string)$request->query->get('search');
        $filter['page']               = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $propertyTypeHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('propertyType', $filter);

        $filter = $propertyTypeHelper->getFilter();

        return new JsonResponse(
            [
                'filter'    => $this->renderView(
                    'PropertyType/_partial/filter.html.twig',
                    [
                        'filter'                 => $filter,
                        'filterStorageLocations' => $propertyTypeHelper->getFilterStorageLocations($filter)
                    ]
                ),
                'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                'success'       => true,
                'template'      => $this->renderView(
                    'PropertyType/_partial/template.html.twig',
                    [
                        'filter'        => $filter,
                        'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                        'totalNames'    => $propertyTypeHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

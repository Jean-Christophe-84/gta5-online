<?php

namespace App\Controller\PropertyAcquisition;

use App\Entity\PropertyAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyAcquisitionHelper;
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
    #[Route(path: '/property-acquisition/filter', name: 'filter_property-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, PropertyAcquisitionHelper $propertyAcquisitionHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $propertyAcquisitionHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('propertyAcquisition', $filter);

        $filter = $propertyAcquisitionHelper->getFilter();

        return new JsonResponse(
            [
                'filter'               => $this->renderView('PropertyAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                'propertyAcquisitions' => $propertyAcquisitionHelper->getPropertyAcquisitions($filter),
                'success'              => true,
                'template'             => $this->renderView(
                    'PropertyAcquisition/_partial/template.html.twig',
                    [
                        'filter'               => $filter,
                        'propertyAcquisitions' => $propertyAcquisitionHelper->getPropertyAcquisitions($filter),
                        'totalNames'           => $propertyAcquisitionHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

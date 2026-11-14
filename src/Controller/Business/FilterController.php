<?php

namespace App\Controller\Business;

use App\Entity\Business;
use App\Helper\BusinessHelper;
use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
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
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/business/filter', name: 'filter_business')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(BusinessHelper $businessHelper, ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack): Response
    {
        if (!$this->isGranted(Business::PERMISSION_BUSINESS_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['titleUpdateId']  = (int)$request->query->get('titleUpdateId');
        $filter['propertyTypeId'] = (int)$request->query->get('propertyTypeId');
        $filter['search']         = (string)$request->query->get('search');
        $filter['page']           = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $businessHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('business', $filter);

        $filter     = $businessHelper->getFilter();
        $businesses = $businessHelper->getBusinesses($filter);

        return new JsonResponse(
            [
                'businesses' => $businesses,
                'filter'     => $this->renderView(
                    'Business/_partial/filter.html.twig',
                    [
                        'filter'                => $filter,
                        'filterPropertyTypeIds' => $businessHelper->getFilterPropertyTypeIds($filter),
                        'filterTitleUpdateIds'  => $businessHelper->getFilterTitleUpdateIds($filter)
                    ]
                ),
                'success'    => true,
                'template'   => $this->renderView(
                    'Business/_partial/template.html.twig',
                    [
                        'businesses' => $businesses,
                        'filter'     => $filter,
                        'totalNames' => $businessHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

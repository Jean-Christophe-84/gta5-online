<?php

namespace App\Controller\BusinessGood;

use App\Entity\BusinessGood;
use App\Helper\BusinessGoodHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/business-good/filter', name: 'filter_business_good')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        BusinessGoodHelper $businessGoodHelper, ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $businessGoodHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('businessGood', $filter);

        $filter        = $businessGoodHelper->getFilter();
        $businessGoods = $businessGoodHelper->getBusinessGoods($filter);

        return new JsonResponse(
            [
                'businessGoods' => $businessGoods,
                'filter'        => $this->renderView('BusinessGood/_partial/filter.html.twig', ['filter' => $filter]),
                'success'       => true,
                'template'      => $this->renderView(
                    'BusinessGood/_partial/template.html.twig',
                    [
                        'businessGoods' => $businessGoods,
                        'filter'        => $filter,
                        'totalNames'    => $businessGoodHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

<?php

namespace App\Controller\DeliveryMethod;

use App\Entity\DeliveryMethod;
use App\Helper\ConfigurationHelper;
use App\Helper\DeliveryMethodHelper;
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
    #[Route(path: '/delivery-method/filter', name: 'filter_delivery-method')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, DeliveryMethodHelper $deliveryMethodHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $deliveryMethodHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('deliveryMethod', $filter);

        $filter          = $deliveryMethodHelper->getFilter();
        $deliveryMethods = $deliveryMethodHelper->getDeliveryMethods($filter);

        return new JsonResponse(
            [
                'deliveryMethods' => $deliveryMethods,
                'filter'          => $this->renderView('DeliveryMethod/_partial/filter.html.twig', ['filter' => $filter]),
                'success'         => true,
                'template'        => $this->renderView(
                    'DeliveryMethod/_partial/template.html.twig',
                    [
                        'deliveryMethods' => $deliveryMethods,
                        'filter'          => $filter,
                        'totalNames'      => $deliveryMethodHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

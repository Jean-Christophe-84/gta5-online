<?php

namespace App\Controller\Customization;

use App\Entity\Customization;
use App\Helper\ConfigurationHelper;
use App\Helper\CustomizationHelper;
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
    #[Route(path: '/customization/filter', name: 'filter_customization')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, CustomizationHelper $customizationHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $customizationHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('customization', $filter);

        $filter         = $customizationHelper->getFilter();
        $customizations = $customizationHelper->getCustomizations($filter);

        return new JsonResponse(
            [
                'customizations' => $customizations,
                'filter'         => $this->renderView('Customization/_partial/filter.html.twig', ['filter' => $filter]),
                'success'        => true,
                'template'       => $this->renderView(
                    'Customization/_partial/template.html.twig',
                    [
                        'customizations' => $customizations,
                        'filter'         => $filter,
                        'totalNames'     => $customizationHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}

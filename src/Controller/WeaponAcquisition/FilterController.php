<?php

namespace App\Controller\WeaponAcquisition;

use App\Entity\WeaponAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponAcquisitionHelper;
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
    #[Route(path: '/weapon-acquisition/filter', name: 'filter_weapon-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, WeaponAcquisitionHelper $weaponAcquisitionHelper
    ): Response {
        if (!$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $weaponAcquisitionHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('weaponAcquisition', $filter);

        $filter = $weaponAcquisitionHelper->getFilter();

        return new JsonResponse(
            [
                'filter'             => $this->renderView('WeaponAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                'success'            => true,
                'template'           => $this->renderView(
                    'WeaponAcquisition/_partial/template.html.twig',
                    [
                        'filter'             => $filter,
                        'totalNames'         => $weaponAcquisitionHelper->getTotalNames(),
                        'weaponAcquisitions' => $weaponAcquisitionHelper->getWeaponAcquisitions($filter)
                    ]
                ),
                'weaponAcquisitions' => $weaponAcquisitionHelper->getWeaponAcquisitions($filter)
            ]
        );
    }
}

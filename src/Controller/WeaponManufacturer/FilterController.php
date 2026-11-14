<?php

namespace App\Controller\WeaponManufacturer;

use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponManufacturerHelper;
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
    #[Route(path: '/weapon-manufacturer/filter', name: 'filter_weapon-manufacturer')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, WeaponManufacturerHelper $weaponManufacturerHelper
    ): Response {
        if (!$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $weaponManufacturerHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('weaponManufacturer', $filter);

        $filter = $weaponManufacturerHelper->getFilter();

        return new JsonResponse(
            [
                'filter'              => $this->renderView('WeaponManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
                'success'             => true,
                'template'            => $this->renderView(
                    'WeaponManufacturer/_partial/template.html.twig',
                    [
                        'filter'              => $filter,
                        'totalNames'          => $weaponManufacturerHelper->getTotalNames(),
                        'weaponManufacturers' => $weaponManufacturerHelper->getWeaponManufacturers($filter)
                    ]
                ),
                'weaponManufacturers' => $weaponManufacturerHelper->getWeaponManufacturers($filter)
            ]
        );
    }
}

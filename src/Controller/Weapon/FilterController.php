<?php

namespace App\Controller\Weapon;

use App\Entity\Weapon;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
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
    #[Route(path: '/weapon/filter', name: 'filter_weapon')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, WeaponHelper $weaponHelper): Response
    {
        if (!$this->isGranted(Weapon::PERMISSION_WEAPON_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['weaponManufacturerId']    = (int)$request->query->get('weaponManufacturerId');
        $filter['titleUpdateId']           = (int)$request->query->get('titleUpdateId');
        $filter['owned']                   = $request->query->get('owned') !== "null" ? (bool)$request->query->get('owned') : null;
        $filter['weaponClassIds']          = $request->query->all('weaponClassIds');
        $filter['weaponAcquisitionIds']    = $request->query->all('weaponAcquisitionIds');
        $filter['notWeaponClassIds']       = $request->query->all('notWeaponClassIds');
        $filter['notWeaponAcquisitionIds'] = $request->query->all('notWeaponAcquisitionIds');
        $filter['weaponTwo']               = (int)$request->query->get('weaponTwo');
        $filter['evolution']               = (int)$request->query->get('evolution');
        $filter['search']                  = (string)$request->query->get('search');
        $filter['page']                    = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $weaponHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('weapon', $filter);

        $parameters = $weaponHelper->getParameters($weaponHelper->getFilter());

        return new JsonResponse(
            [
                'filter'    => $this->renderView('Weapon/_partial/filter.html.twig', $parameters),
                'filterTwo' => $this->renderView('Weapon/_partial/filter_two.html.twig', $parameters),
                'success'   => true,
                'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'weapons', 'summary' => $parameters['summary']]),
                'template'  => $this->renderView('Weapon/_partial/template.html.twig', $parameters),
                'weapons'   => $parameters['weapons']
            ]
        );
    }
}

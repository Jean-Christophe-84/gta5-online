<?php

namespace App\Controller\WeaponManufacturer;

use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponManufacturerHelper;
use App\Service\DeletionResponder;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/weapon-manufacturer/get', name: 'get_weapon-manufacturer')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getWeaponManufacturer(
        ConfigurationHelper      $configurationHelper, DeletionResponder $deletionResponder, Request $request,
        WeaponManufacturerHelper $weaponManufacturerHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('weaponManufacturerId')) {
            if (!$this->isGranted($delete ? WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_DELETE : WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weaponManufacturer = $weaponManufacturerHelper->getWeaponManufacturer($request->query->get('weaponManufacturerId'));
        } else {
            if ($delete || !$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weaponManufacturer = null;
        }

        $template = $this->renderView('WeaponManufacturer/_partial/body.html.twig', ['weaponManufacturer' => $weaponManufacturer]);

        if ($delete) {
            return $deletionResponder->json($template, $weaponManufacturerHelper->getDeletion((int)$request->query->get('weaponManufacturerId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

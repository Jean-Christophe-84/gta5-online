<?php

namespace App\Controller\WeaponAcquisition;

use App\Entity\WeaponAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponAcquisitionHelper;
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
    #[Route(path: '/weapon-acquisition/get', name: 'get_weapon-acquisition')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getWeaponAcquisition(
        ConfigurationHelper     $configurationHelper, DeletionResponder $deletionResponder, Request $request,
        WeaponAcquisitionHelper $weaponAcquisitionHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('weaponAcquisitionId')) {
            if (!$this->isGranted($delete ? WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_DELETE : WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weaponAcquisition = $weaponAcquisitionHelper->getWeaponAcquisition($request->query->get('weaponAcquisitionId'));
        } else {
            if ($delete || !$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weaponAcquisition = null;
        }

        $template = $this->renderView('WeaponAcquisition/_partial/body.html.twig', ['weaponAcquisition' => $weaponAcquisition]);

        if ($delete) {
            return $deletionResponder->json($template, $weaponAcquisitionHelper->getDeletion((int)$request->query->get('weaponAcquisitionId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

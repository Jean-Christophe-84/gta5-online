<?php

namespace App\Controller\WeaponManufacturer;

use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponManufacturerHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/weapon-manufacturer/delete', name: 'delete_weapon-manufacturer', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, WeaponManufacturerHelper $weaponManufacturerHelper
    ): Response {
        if (!$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $weaponManufacturerId = (int)$request->request->get('weaponManufacturerId');

        if (!$weaponManufacturer = $weaponManufacturerHelper->getWeaponManufacturer($weaponManufacturerId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($weaponManufacturerHelper->getDeletion($weaponManufacturerId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $weaponManufacturerHelper->delete($weaponManufacturerId);

            $filter = $weaponManufacturerHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'              => $this->renderView('WeaponManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'             => ucfirst($configurationHelper->getTranslateLocale('success_weapon_manufacturer_delete', ['name' => $weaponManufacturer['name']])),
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
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_weapon_manufacturer_delete', ['name' => $weaponManufacturer['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

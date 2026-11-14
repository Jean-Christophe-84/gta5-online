<?php

namespace App\Controller\WeaponManufacturer;

use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponManufacturerHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/weapon-manufacturer/form', name: 'form_weapon-manufacturer', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper      $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        WeaponManufacturerHelper $weaponManufacturerHelper
    ): Response {
        $type = $request->request->get('weaponManufacturerId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($type === 'update') {
                if (!$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weaponManufacturer =
                    $entityManager->getRepository(WeaponManufacturer::class)->find($request->request->get('weaponManufacturerId'));
            } else {
                if (!$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weaponManufacturer = new WeaponManufacturer();
            }
            $weaponManufacturer->setName(trim($request->request->get('name')));

            $entityManager->persist($weaponManufacturer);
            $entityManager->flush();

            $filter = $weaponManufacturerHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'              => $this->renderView('WeaponManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'             => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_weapon_manufacturer_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
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
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_weapon_manufacturer_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

<?php

namespace App\Controller\Weapon;

use App\Entity\CustomizationLocale;
use App\Entity\Locale;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Entity\Weapon;
use App\Entity\WeaponAcquisitionLocale;
use App\Entity\WeaponClassLocale;
use App\Entity\WeaponLocale;
use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
use App\Service\DeletionResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
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
     * @throws DoctrineDBALException
     */
    #[Route(path: '/weapon/get', name: 'get_weapon')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getWeapon(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, EntityManagerInterface $entityManager, Request $request,
        WeaponHelper        $weaponHelper
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('weaponId')) {
            if (!$this->isGranted($delete ? Weapon::PERMISSION_WEAPON_DELETE : Weapon::PERMISSION_WEAPON_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weapon = $weaponHelper->getWeapon($request->query->get('weaponId'));
        } else {
            if ($delete || !$this->isGranted(Weapon::PERMISSION_WEAPON_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $weapon = null;
        }

        $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

        $template = $this->renderView(
            'Weapon/_partial/body.html.twig',
            [
                'customizationLocales'     => $entityManager->getRepository(CustomizationLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'titleUpdates'             => $entityManager->getRepository(TitleUpdate::class)->findBy([], ['name' => 'ASC']),
                'weapon'                   => $weapon,
                'weaponAcquisitionLocales' => $entityManager->getRepository(WeaponAcquisitionLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'weaponClassLocales'       => $entityManager->getRepository(WeaponClassLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'weaponManufacturers'      => $entityManager->getRepository(WeaponManufacturer::class)->findBy([], ['name' => 'ASC']),
                'weaponOneLocales'         => $entityManager->getRepository(WeaponLocale::class)->findBy([], ['name' => 'ASC'])
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $weaponHelper->getDeletion((int)$request->query->get('weaponId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

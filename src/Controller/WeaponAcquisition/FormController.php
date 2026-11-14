<?php

namespace App\Controller\WeaponAcquisition;

use App\Entity\Locale;
use App\Entity\User;
use App\Entity\WeaponAcquisition;
use App\Entity\WeaponAcquisitionLocale;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponAcquisitionHelper;
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
    #[Route(path: '/weapon-acquisition/form', name: 'form_weapon-acquisition', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper     $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        WeaponAcquisitionHelper $weaponAcquisitionHelper
    ): Response {
        $type = $request->request->get('weaponAcquisitionId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weaponAcquisition = $entityManager->getRepository(WeaponAcquisition::class)->find($request->request->get('weaponAcquisitionId'));
            } else {
                if (!$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weaponAcquisition = new WeaponAcquisition();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $weaponAcquisitionLocale = $entityManager->getRepository(WeaponAcquisitionLocale::class)->findOneBy(
                ['weaponAcquisition' => $weaponAcquisition, 'locale' => $locale]
            );

            if (!$weaponAcquisitionLocale instanceof WeaponAcquisitionLocale) {
                $weaponAcquisitionLocale = new WeaponAcquisitionLocale();
                $weaponAcquisitionLocale->setWeaponAcquisition($weaponAcquisition);
                $weaponAcquisitionLocale->setLocale($locale);
            }
            $weaponAcquisitionLocale->setName(trim($request->request->get('name')));

            $weaponAcquisition->setPromotion((bool)$request->request->get('promotion'));

            $entityManager->persist($weaponAcquisitionLocale);
            $entityManager->persist($weaponAcquisition);
            $entityManager->flush();

            $filter = $weaponAcquisitionHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'             => $this->renderView('WeaponAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'            => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_weapon_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
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
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_weapon_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

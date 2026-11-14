<?php

namespace App\Controller\Weapon;

use App\Entity\Customization;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Entity\Weapon;
use App\Entity\WeaponAcquisition;
use App\Entity\WeaponClass;
use App\Entity\WeaponCustomization;
use App\Entity\WeaponLocale;
use App\Entity\WeaponManufacturer;
use App\Entity\WeaponUser;
use App\Entity\WeaponWeapon;
use App\Entity\WeaponWeaponAcquisition;
use App\Entity\WeaponWeaponClass;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
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
    #[Route(path: '/weapon/form', name: 'form_weapon', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        WeaponHelper        $weaponHelper
    ): Response {
        $type = $request->request->get('weaponId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($request->request->get('weaponManufacturerId')) {
                $weaponManufacturer = $entityManager->getRepository(WeaponManufacturer::class)->find($request->request->get('weaponManufacturerId'));
            } else {
                $weaponManufacturer = null;
            }

            if ($request->request->get('titleUpdateId')) {
                $titleUpdate = $entityManager->getRepository(TitleUpdate::class)->find($request->request->get('titleUpdateId'));
            } else {
                $titleUpdate = null;
            }

            if ($type === 'update') {
                if (!$this->isGranted(Weapon::PERMISSION_WEAPON_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weapon = $entityManager->getRepository(Weapon::class)->find($request->request->get('weaponId'));
            } else {
                if (!$this->isGranted(Weapon::PERMISSION_WEAPON_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $weapon = new Weapon();
            }

            /** @var User $user */
            $user = $this->getUser();

            $weaponLocale = $entityManager->getRepository(WeaponLocale::class)->findOneBy(['weapon' => $weapon, 'locale' => $user->getLocale()]);

            if (!$weaponLocale instanceof WeaponLocale) {
                $weaponLocale = new WeaponLocale();
                $weaponLocale->setWeapon($weapon);
                $weaponLocale->setLocale($user->getLocale());
            }

            $weaponLocale->setName(trim($request->request->get('name')));
            $weaponLocale->setComment(trim($request->request->get('comment')));
            $weaponLocale->setUnlockWeapon(trim($request->request->get('unlockWeapon')));

            $entityManager->persist($weaponLocale);

            if (!($weaponUser = $entityManager->getRepository(WeaponUser::class)->findOneBy(['user' => $user, 'weapon' => $weapon])) instanceof WeaponUser) {
                $weaponUser = new WeaponUser();
                $weaponUser->setUser($user);
                $weaponUser->setWeapon($weapon);
            }

            $weaponUser->setOwned($request->request->get('owned'));

            $entityManager->persist($weaponUser);

            $weapon->setWeaponManufacturer($weaponManufacturer);
            $weapon->setEvolution($request->request->get('evolution'));
            $weapon->setPrice($request->request->get('price') ? : null);
            $weapon->setDiscountedPrice($request->request->get('discountedPrice') ? : null);
            $weapon->setDiscountedPercentage($request->request->get('discountedPercentage') ? : null);
            $weapon->setTitleUpdate($titleUpdate);
            $weapon->setDamage($request->request->get('damage') ? : null);
            $weapon->setFireRate($request->request->get('fireRate') ? : null);
            $weapon->setAccuracy($request->request->get('accuracy') ? : null);
            $weapon->setRangeWeapon($request->request->get('rangeWeapon') ? : null);
            $weapon->setClipSize($request->request->get('clipSize') ? : null);
            $weapon->setRealLifeWeapon(trim($request->request->get('realLifeWeapon')) ? : null);

            if ($this->isGranted('ROLE_ADMIN')) {
                $weapon->setUrl(trim($request->request->get('url')) ? : null);
            }

            $weapon->setUnlockRank(trim($request->request->get('unlockRank')) ? : null);

            $entityManager->persist($weapon);
            $entityManager->flush();

            $weaponWeaponOnesDb         =
                $configurationHelper->indexByEntityId($entityManager->getRepository(WeaponWeapon::class)->findBy(['weaponTwo' => $weapon]), 'WeaponOne');
            $weaponWeaponClassesDb      =
                $configurationHelper->indexByEntityId($entityManager->getRepository(WeaponWeaponClass::class)->findBy(['weapon' => $weapon]), 'WeaponClass');
            $weaponWeaponAcquisitionsDb = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(WeaponWeaponAcquisition::class)->findBy(['weapon' => $weapon]), 'WeaponAcquisition'
            );
            $weaponCustomizationsDb     = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(WeaponCustomization::class)->findBy(['weapon' => $weapon]), 'Customization'
            );

            // Weapon twos
            if (!empty($request->request->all('weaponOneIds'))) {
                $positionWeaponWeapon = 1;

                foreach ($request->request->all('weaponOneIds') as $weaponOneId) {
                    if (($weaponOne = $entityManager->getRepository(Weapon::class)->find($weaponOneId)) instanceof Weapon) {
                        if (isset($weaponWeaponOnesDb[$weaponOneId])) {
                            $weaponWeapon = $weaponWeaponOnesDb[$weaponOneId];
                            unset($weaponWeaponOnesDb[$weaponOneId]);
                        } else {
                            $weaponWeapon = new WeaponWeapon();
                            $weaponWeapon->setWeaponOne($weaponOne);
                            $weaponWeapon->setWeaponTwo($weapon);
                        }

                        $weaponWeapon->setPosition($positionWeaponWeapon);

                        $entityManager->persist($weaponWeapon);

                        $positionWeaponWeapon++;
                    }
                }
            }

            foreach ($weaponWeaponOnesDb as $weaponWeaponOneToRemove) {
                $entityManager->remove($weaponWeaponOneToRemove);
            }

            // Weapon classes
            if (!empty($request->request->all('weaponClassIds'))) {
                $positionWeaponWeaponClass = 1;

                foreach ($request->request->all('weaponClassIds') as $weaponClassId) {
                    if (($weaponClass = $entityManager->getRepository(WeaponClass::class)->find($weaponClassId)) instanceof WeaponClass) {
                        if (isset($weaponWeaponClassesDb[$weaponClassId])) {
                            $weaponWeaponClass = $weaponWeaponClassesDb[$weaponClassId];
                            unset($weaponWeaponClassesDb[$weaponClassId]);
                        } else {
                            $weaponWeaponClass = new WeaponWeaponClass();
                            $weaponWeaponClass->setWeapon($weapon);
                            $weaponWeaponClass->setWeaponClass($weaponClass);
                        }

                        $weaponWeaponClass->setPosition($positionWeaponWeaponClass);

                        $entityManager->persist($weaponWeaponClass);

                        $positionWeaponWeaponClass++;
                    }
                }
            }

            foreach ($weaponWeaponClassesDb as $weaponWeaponClassToRemove) {
                $entityManager->remove($weaponWeaponClassToRemove);
            }

            // Weapon Acquisitions
            if (!empty($request->request->all('weaponAcquisitionIds'))) {
                $positionWeaponWeaponAcquisition = 1;

                foreach ($request->request->all('weaponAcquisitionIds') as $weaponAcquisitionId) {
                    $weaponAcquisition = $entityManager->getRepository(WeaponAcquisition::class)->find($weaponAcquisitionId);

                    if ($weaponAcquisition instanceof WeaponAcquisition) {
                        if (isset($weaponWeaponAcquisitionsDb[$weaponAcquisitionId])) {
                            $weaponWeaponAcquisition = $weaponWeaponAcquisitionsDb[$weaponAcquisitionId];
                            unset($weaponWeaponAcquisitionsDb[$weaponAcquisitionId]);
                        } else {
                            $weaponWeaponAcquisition = new WeaponWeaponAcquisition();
                            $weaponWeaponAcquisition->setWeapon($weapon);
                            $weaponWeaponAcquisition->setWeaponAcquisition($weaponAcquisition);
                        }

                        $weaponWeaponAcquisition->setPosition($positionWeaponWeaponAcquisition);

                        $entityManager->persist($weaponWeaponAcquisition);

                        $positionWeaponWeaponAcquisition++;
                    }
                }
            }

            foreach ($weaponWeaponAcquisitionsDb as $weaponWeaponAcquisitionToRemove) {
                $entityManager->remove($weaponWeaponAcquisitionToRemove);
            }

            // Customizations
            if (!empty($request->request->all('customizationIds'))) {
                $positionWeaponCustomization = 1;

                foreach ($request->request->all('customizationIds') as $customizationId) {
                    if (($customization = $entityManager->getRepository(Customization::class)->find($customizationId)) instanceof Customization) {
                        if (isset($weaponCustomizationsDb[$customizationId])) {
                            $weaponCustomization = $weaponCustomizationsDb[$customizationId];
                            unset($weaponCustomizationsDb[$customizationId]);
                        } else {
                            $weaponCustomization = new WeaponCustomization();
                            $weaponCustomization->setWeapon($weapon);
                            $weaponCustomization->setCustomization($customization);
                        }

                        $weaponCustomization->setPosition($positionWeaponCustomization);

                        $entityManager->persist($weaponCustomization);

                        $positionWeaponCustomization++;
                    }
                }
            }

            foreach ($weaponCustomizationsDb as $weaponCustomizationToRemove) {
                $entityManager->remove($weaponCustomizationToRemove);
            }

            $entityManager->flush();

            if ($request->request->get('owned') && $request->request->get('owned') !== "0") {
                $weaponHelper->releaseLinkedEvolutions($weapon->id);
            }

            $parameters = $weaponHelper->getParameters($weaponHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Weapon/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Weapon/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_weapon_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'weapons', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Weapon/_partial/template.html.twig', $parameters),
                    'weapons'   => $parameters['weapons']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_weapon_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

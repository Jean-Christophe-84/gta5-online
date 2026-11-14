<?php

namespace App\Controller\BusinessGood;

use App\Entity\Business;
use App\Entity\BusinessGood;
use App\Entity\BusinessGoodLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\BusinessGoodHelper;
use App\Helper\BusinessHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/business-good/form', name: 'form_business_good', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        BusinessGoodHelper     $businessGoodHelper, BusinessHelper $businessHelper, ConfigurationHelper $configurationHelper,
        EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request
    ): Response {
        $type = $request->request->get('businessGoodId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['businessId' => 'please_select_a_business'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        $businessName = $businessHelper->getBusiness((int)$request->request->get('businessId'))['name'] ?? '';

        try {
            if ($request->request->get('businessId')) {
                $business = $entityManager->getRepository(Business::class)->find($request->request->get('businessId'));
            } else {
                $business = null;
            }

            if ($type === 'update') {
                if (!$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $businessGood = $entityManager->getRepository(BusinessGood::class)->find($request->request->get('businessGoodId'));
            } else {
                if (!$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $businessGood = new BusinessGood();
            }

            $businessGood->setBusiness($business);
            $businessGood->setStockDealer($request->request->get('stockDealer') ? : null);
            $businessGood->setStockDealerTotal($request->request->get('stockDealerTotal') ? : null);
            $businessGood->setCostOfRestock($request->request->get('costOfRestock') ? : null);
            $businessGood->setReplenishmentRate($request->request->get('replenishmentRate') ? : null);
            $businessGood->setReplenishmentRateWithUpgradedEquipment($request->request->get('replenishmentRateWithUpgradedEquipment') ? : null);
            $businessGood->setReplenishmentRateWithUpgradedStaff($request->request->get('replenishmentRateWithUpgradedStaff') ? : null);
            $businessGood->setReplenishmentRateWithUpgradedEquipmentAndStaff($request->request->get('replenishmentRateWithUpgradedEquipmentAndStaff') ? : null);
            $businessGood->setSale($request->request->get('sale') ? : null);
            $businessGood->setSaleWithUpgradedEquipment($request->request->get('saleWithUpgradedEquipment') ? : null);
            $businessGood->setSaleWithUpgradedStaff($request->request->get('saleWithUpgradedStaff') ? : null);
            $businessGood->setSaleWithUpgradedEquipmentAndStaff($request->request->get('saleWithUpgradedEquipmentAndStaff') ? : null);
            $businessGood->setTimeForRestock($request->request->get('timeForRestock') ? : null);
            $businessGood->setTimeForRestockWithUpgradedEquipment($request->request->get('timeForRestockWithUpgradedEquipment') ? : null);
            $businessGood->setTimeForRestockWithUpgradedStaff($request->request->get('timeForRestockWithUpgradedStaff') ? : null);
            $businessGood->setTimeForRestockWithUpgradedEquipmentAndStaff($request->request->get('timeForRestockWithUpgradedEquipmentAndStaff') ? : null);

            $entityManager->persist($businessGood);
            $entityManager->flush();

            /** @var User $user */
            $user   = $this->getUser();
            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $businessGoodLocale = $entityManager->getRepository(BusinessGoodLocale::class)->findOneBy(['businessGood' => $businessGood, 'locale' => $locale]);

            if (!$businessGoodLocale instanceof BusinessGoodLocale) {
                $businessGoodLocale = new BusinessGoodLocale();
                $businessGoodLocale->setBusinessGood($businessGood);
                $businessGoodLocale->setLocale($locale);
            }

            $businessGoodLocale->setAdditionalInformations(trim((string)$request->request->get('additionalInformations')) ? : null);

            $entityManager->persist($businessGoodLocale);
            $entityManager->flush();

            $filter        = $businessGoodHelper->getFilter();
            $businessGoods = $businessGoodHelper->getBusinessGoods($filter);

            return new JsonResponse(
                [
                    'businessGoods' => $businessGoods,
                    'filter'        => $this->renderView('BusinessGood/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'       => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_business_good_form', ['name' => $businessName, 'type' => $type]
                        )
                    ),
                    'success'       => true,
                    'template'      => $this->renderView(
                        'BusinessGood/_partial/template.html.twig',
                        [
                            'businessGoods' => $businessGoods,
                            'filter'        => $filter,
                            'totalNames'    => $businessGoodHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_business_good_form', ['name' => $businessName, 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

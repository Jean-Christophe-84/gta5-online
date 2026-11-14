<?php

namespace App\Controller\Business;

use App\Entity\Business;
use App\Entity\BusinessLocale;
use App\Entity\Locale;
use App\Entity\PropertyType;
use App\Entity\TitleUpdate;
use App\Entity\User;
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
    #[Route(path: '/business/form', name: 'form_business', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        BusinessHelper $businessHelper, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager,
        ErrorResponder $errorResponder, Request $request
    ): Response {
        $type = $request->request->get('businessId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'name'           => 'please_enter_the_name',
                'propertyTypeId' => 'please_enter_the_property_type',
                'maximumNumber'  => 'please_enter_the_maximum_number',
                'titleUpdateId'  => 'please_enter_the_title_update'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(Business::PERMISSION_BUSINESS_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $business = $entityManager->getRepository(Business::class)->find($request->request->get('businessId'));
            } else {
                if (!$this->isGranted(Business::PERMISSION_BUSINESS_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $business = new Business();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $businessLocale = $entityManager->getRepository(BusinessLocale::class)->findOneBy(['business' => $business, 'locale' => $locale]);

            if (!$businessLocale instanceof BusinessLocale) {
                $businessLocale = new BusinessLocale();
                $businessLocale->setBusiness($business);
                $businessLocale->setLocale($locale);
            }
            $businessLocale->setName(trim($request->request->get('name')));
            $businessLocale->setContact($request->request->get('contact') ? : null);
            $businessLocale->setDailyEarnings($request->request->get('dailyEarnings') ? : null);
            $businessLocale->setDailyExpenses($request->request->get('dailyExpenses') ? : null);
            $businessLocale->setImprovement($request->request->get('improvement') ? : null);

            $business->setPropertyType($entityManager->getRepository(PropertyType::class)->find($request->request->get('propertyTypeId')));
            $business->setMaximumNumber($request->request->get('maximumNumber'));
            $business->setTitleUpdate($entityManager->getRepository(TitleUpdate::class)->find($request->request->get('titleUpdateId')));

            $entityManager->persist($businessLocale);
            $entityManager->persist($business);
            $entityManager->flush();

            $filter     = $businessHelper->getFilter();
            $businesses = $businessHelper->getBusinesses($filter);

            return new JsonResponse(
                [
                    'businesses' => $businesses,
                    'filter'     => $this->renderView(
                        'Business/_partial/filter.html.twig',
                        [
                            'filter'                => $filter,
                            'filterPropertyTypeIds' => $businessHelper->getFilterPropertyTypeIds($filter),
                            'filterTitleUpdateIds'  => $businessHelper->getFilterTitleUpdateIds($filter)
                        ]
                    ),
                    'message'    => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_business_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'    => true,
                    'template'   => $this->renderView(
                        'Business/_partial/template.html.twig',
                        [
                            'businesses' => $businesses,
                            'filter'     => $filter,
                            'totalNames' => $businessHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_business_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

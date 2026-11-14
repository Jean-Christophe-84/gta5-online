<?php

namespace App\Controller\PropertyAcquisition;

use App\Entity\Locale;
use App\Entity\PropertyAcquisition;
use App\Entity\PropertyAcquisitionLocale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyAcquisitionHelper;
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
    #[Route(path: '/property-acquisition/form', name: 'form_property-acquisition', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper       $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        PropertyAcquisitionHelper $propertyAcquisitionHelper, Request $request
    ): Response {
        $type = $request->request->get('propertyAcquisitionId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $propertyAcquisition = $entityManager->getRepository(PropertyAcquisition::class)->find($request->request->get('propertyAcquisitionId'));
            } else {
                if (!$this->isGranted(PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $propertyAcquisition = new PropertyAcquisition();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $propertyAcquisitionLocale = $entityManager->getRepository(PropertyAcquisitionLocale::class)->findOneBy(
                ['propertyAcquisition' => $propertyAcquisition, 'locale' => $locale]
            );

            if (!$propertyAcquisitionLocale instanceof PropertyAcquisitionLocale) {
                $propertyAcquisitionLocale = new PropertyAcquisitionLocale();
                $propertyAcquisitionLocale->setPropertyAcquisition($propertyAcquisition);
                $propertyAcquisitionLocale->setLocale($locale);
            }
            $propertyAcquisitionLocale->setName(trim($request->request->get('name')));

            $propertyAcquisition->setPromotion((bool)$request->request->get('promotion'));

            $entityManager->persist($propertyAcquisitionLocale);
            $entityManager->persist($propertyAcquisition);
            $entityManager->flush();

            $filter = $propertyAcquisitionHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'               => $this->renderView('PropertyAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'              => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_property_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'propertyAcquisitions' => $propertyAcquisitionHelper->getPropertyAcquisitions($filter),
                    'success'              => true,
                    'template'             => $this->renderView(
                        'PropertyAcquisition/_partial/template.html.twig',
                        [
                            'filter'               => $filter,
                            'propertyAcquisitions' => $propertyAcquisitionHelper->getPropertyAcquisitions($filter),
                            'totalNames'           => $propertyAcquisitionHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_property_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

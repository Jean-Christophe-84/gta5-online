<?php

namespace App\Controller\Customization;

use App\Entity\Customization;
use App\Entity\CustomizationLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\CustomizationHelper;
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
    #[Route(path: '/customization/form', name: 'form_customization', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, CustomizationHelper $customizationHelper,
        ErrorResponder      $errorResponder, Request $request
    ): Response {
        $type = $request->request->get('customizationId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $customization = $entityManager->getRepository(Customization::class)->find($request->request->get('customizationId'));
            } else {
                if (!$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $customization = new Customization();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $customizationLocale = $entityManager->getRepository(CustomizationLocale::class)->findOneBy(
                ['customization' => $customization, 'locale' => $locale]
            );

            if (!$customizationLocale instanceof CustomizationLocale) {
                $customizationLocale = new CustomizationLocale();
                $customizationLocale->setCustomization($customization);
                $customizationLocale->setLocale($locale);
            }
            $customizationLocale->setName(trim($request->request->get('name')));

            $entityManager->persist($customizationLocale);
            $entityManager->persist($customization);
            $entityManager->flush();

            $filter         = $customizationHelper->getFilter();
            $customizations = $customizationHelper->getCustomizations($filter);

            return new JsonResponse(
                [
                    'customizations' => $customizations,
                    'filter'         => $this->renderView('Customization/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'        => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_customization_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'        => true,
                    'template'       => $this->renderView(
                        'Customization/_partial/template.html.twig',
                        [
                            'customizations' => $customizations,
                            'filter'         => $filter,
                            'totalNames'     => $customizationHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_customization_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

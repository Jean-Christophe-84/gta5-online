<?php

namespace App\Controller\ModificationLocation;

use App\Entity\Locale;
use App\Entity\ModificationLocation;
use App\Entity\ModificationLocationLocale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\ModificationLocationHelper;
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
    #[Route(path: '/modification-location/form', name: 'form_modification-location', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper        $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        ModificationLocationHelper $modificationLocationHelper, Request $request
    ): Response {
        $type = $request->request->get('modificationLocationId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $modificationLocation = $entityManager->getRepository(ModificationLocation::class)->find($request->request->get('modificationLocationId'));
            } else {
                if (!$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $modificationLocation = new ModificationLocation();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            if (!($modificationLocationLocale =
                $entityManager->getRepository(ModificationLocationLocale::class)->findOneBy(
                    ['modificationLocation' => $modificationLocation, 'locale' => $locale]
                )) instanceof ModificationLocationLocale
            ) {
                $modificationLocationLocale = new ModificationLocationLocale();
                $modificationLocationLocale->setModificationLocation($modificationLocation);
                $modificationLocationLocale->setLocale($locale);
            }
            $modificationLocationLocale->setName(trim($request->request->get('name')));

            $entityManager->persist($modificationLocationLocale);
            $entityManager->persist($modificationLocation);
            $entityManager->flush();

            $filter         = $modificationLocationHelper->getFilter();
            $modificationLocations = $modificationLocationHelper->getModificationLocations($filter);

            return new JsonResponse(
                [
                    'filter'                => $this->renderView('ModificationLocation/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'               => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_modification_location_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'modificationLocations' => $modificationLocations,
                    'success'               => true,
                    'template'              => $this->renderView(
                        'ModificationLocation/_partial/template.html.twig',
                        [
                            'filter'                => $filter,
                            'modificationLocations' => $modificationLocations,
                            'totalNames'            => $modificationLocationHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_modification_location_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

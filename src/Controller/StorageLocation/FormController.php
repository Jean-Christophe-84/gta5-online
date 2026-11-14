<?php

namespace App\Controller\StorageLocation;

use App\Entity\Locale;
use App\Entity\StorageLocation;
use App\Entity\StorageLocationLocale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\StorageLocationHelper;
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
    #[Route(path: '/storage-location/form', name: 'form_storage-location', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper   $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        StorageLocationHelper $storageLocationHelper, Request $request
    ): Response {
        $type = $request->request->get('storageLocationId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(StorageLocation::PERMISSION_STORAGE_LOCATION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $storageLocation = $entityManager->getRepository(StorageLocation::class)->find($request->request->get('storageLocationId'));
            } else {
                if (!$this->isGranted(StorageLocation::PERMISSION_STORAGE_LOCATION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $storageLocation = new StorageLocation();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $storageLocationLocale = $entityManager->getRepository(StorageLocationLocale::class)->findOneBy(
                ['storageLocation' => $storageLocation, 'locale' => $locale]
            );

            if (!$storageLocationLocale instanceof StorageLocationLocale) {
                $storageLocationLocale = new StorageLocationLocale();
                $storageLocationLocale->setStorageLocation($storageLocation);
                $storageLocationLocale->setLocale($locale);
            }
            $storageLocationLocale->setName(trim($request->request->get('name')));

            $storageLocation->setPropertyRequired((bool)$request->request->get('propertyRequired'));

            $entityManager->persist($storageLocationLocale);
            $entityManager->persist($storageLocation);
            $entityManager->flush();

            $filter           = $storageLocationHelper->getFilter();
            $storageLocations = $storageLocationHelper->getStorageLocations($filter);

            return new JsonResponse(
                [
                    'filter'           => $this->renderView('StorageLocation/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'          => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_storage_location_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'storageLocations' => $storageLocations,
                    'success'          => true,
                    'template'         => $this->renderView(
                        'StorageLocation/_partial/template.html.twig',
                        [
                            'filter'           => $filter,
                            'storageLocations' => $storageLocations,
                            'totalNames'       => $storageLocationHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_storage_location_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

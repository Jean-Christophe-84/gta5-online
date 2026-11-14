<?php

namespace App\Controller\PropertyType;

use App\Entity\Locale;
use App\Entity\PropertyType;
use App\Entity\PropertyTypeLocale;
use App\Entity\PropertyTypeStorageLocation;
use App\Entity\StorageLocation;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyTypeHelper;
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
    #[Route(path: '/property-type/form', name: 'form_property-type', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        PropertyTypeHelper  $propertyTypeHelper, Request $request
    ): Response {
        $type = $request->request->get('propertyTypeId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $propertyType = $entityManager->getRepository(PropertyType::class)->find($request->request->get('propertyTypeId'));
            } else {
                if (!$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $propertyType = new PropertyType();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $propertyTypeLocale = $entityManager->getRepository(PropertyTypeLocale::class)->findOneBy(['propertyType' => $propertyType, 'locale' => $locale]);

            if (!$propertyTypeLocale instanceof PropertyTypeLocale) {
                $propertyTypeLocale = new PropertyTypeLocale();
                $propertyTypeLocale->setPropertyType($propertyType);
                $propertyTypeLocale->setLocale($locale);
            }
            $propertyTypeLocale->setName(trim($request->request->get('name')));

            $entityManager->persist($propertyTypeLocale);
            $entityManager->persist($propertyType);
            $entityManager->flush();

            $propertyTypeStorageLocationDb = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(PropertyTypeStorageLocation::class)->findBy(['propertyType' => $propertyType]), 'StorageLocation'
            );

            // Storage locations
            foreach ($request->request->all('storageLocationIds') as $storageLocationId) {
                if (($storageLocation = $entityManager->getRepository(StorageLocation::class)->find($storageLocationId)) instanceof StorageLocation) {
                    if (!isset($propertyTypeStorageLocationDb[$storageLocationId])) {
                        $propertyTypeStorageLocation = new PropertyTypeStorageLocation();
                        $propertyTypeStorageLocation->setPropertyType($propertyType);
                        $propertyTypeStorageLocation->setStorageLocation($storageLocation);

                        $entityManager->persist($propertyTypeStorageLocation);
                    }
                }
            }

            foreach ($propertyTypeStorageLocationDb as $propertyTypeStorageLocationToRemove) {
                $entityManager->remove($propertyTypeStorageLocationToRemove);
            }

            $entityManager->flush();

            $filter = $propertyTypeHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'        => $this->renderView(
                        'PropertyType/_partial/filter.html.twig',
                        [
                            'filter'                 => $filter,
                            'filterStorageLocations' => $propertyTypeHelper->getFilterStorageLocations($filter)
                        ]
                    ),
                    'message'       => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_property_type_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                    'success'       => true,
                    'template'      => $this->renderView(
                        'PropertyType/_partial/template.html.twig',
                        [
                            'filter'        => $filter,
                            'propertyTypes' => $propertyTypeHelper->getPropertyTypes($filter),
                            'totalNames'    => $propertyTypeHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_property_type_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

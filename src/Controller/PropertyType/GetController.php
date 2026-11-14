<?php

namespace App\Controller\PropertyType;

use App\Entity\Locale;
use App\Entity\PropertyType;
use App\Entity\StorageLocationLocale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyTypeHelper;
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
    #[Route(path: '/property-type/get', name: 'get_property-type')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getPropertyType(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, EntityManagerInterface $entityManager,
        PropertyTypeHelper  $propertyTypeHelper, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('propertyTypeId')) {
            if (!$this->isGranted($delete ? PropertyType::PERMISSION_PROPERTY_TYPE_DELETE : PropertyType::PERMISSION_PROPERTY_TYPE_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }
        } else {
            if ($delete || !$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }
        }

        /** @var User $user */
        $user   = $this->getUser();
        $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

        $propertyType = null;

        if ($request->query->get('propertyTypeId')) {
            $propertyType = $propertyTypeHelper->getPropertyType($request->query->get('propertyTypeId'));
        }

        $template = $this->renderView(
            'PropertyType/_partial/body.html.twig',
            [
                'propertyType'           => $propertyType,
                'storageLocationLocales' => $entityManager->getRepository(StorageLocationLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $propertyTypeHelper->getDeletion((int)$request->query->get('propertyTypeId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

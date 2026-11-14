<?php

namespace App\Controller\Property;

use App\Entity\Locale;
use App\Entity\Property;
use App\Entity\PropertyAcquisitionLocale;
use App\Entity\PropertyArea;
use App\Entity\PropertyLocation;
use App\Entity\PropertyTypeLocale;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
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
    #[Route(path: '/property/get', name: 'get_property')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getProperty(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, EntityManagerInterface $entityManager,
        PropertyHelper      $propertyHelper, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('propertyId')) {
            if (!$this->isGranted($delete ? Property::PERMISSION_PROPERTY_DELETE : Property::PERMISSION_PROPERTY_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }
        } else {
            if ($delete || !$this->isGranted(Property::PERMISSION_PROPERTY_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }
        }

        /** @var User $user */
        $user   = $this->getUser();
        $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

        $template = $this->renderView(
            'Property/_partial/body.html.twig',
            [
                'businesses'                 => $propertyHelper->getBusinessMaximums((int)$request->query->get('propertyId') ? : null),
                'parentPropertyMessage'      => $propertyHelper->getParentPropertyMessage((int)$request->query->get('propertyId') ? : null, true),
                'property'                   => $request->query->get('propertyId') ? $propertyHelper->getProperty(
                    $request->query->get('propertyId')
                ) : null,
                'propertyAcquisitionLocales' => $entityManager->getRepository(PropertyAcquisitionLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'propertyAreas'              => $entityManager->getRepository(PropertyArea::class)->findBy([], ['name' => 'ASC']),
                'propertyLocations'          => $entityManager->getRepository(PropertyLocation::class)->findBy([], ['name' => 'ASC']),
                'propertyTwos'               => $entityManager->getRepository(Property::class)->findBy([], ['name' => 'ASC']),
                'propertyTypeLocales'        => $entityManager->getRepository(PropertyTypeLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'titleUpdates'               => $entityManager->getRepository(TitleUpdate::class)->findBy([], ['name' => 'ASC'])
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $propertyHelper->getDeletion((int)$request->query->get('propertyId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

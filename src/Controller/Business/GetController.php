<?php

namespace App\Controller\Business;

use App\Entity\Business;
use App\Entity\PropertyTypeLocale;
use App\Entity\TitleUpdate;
use App\Helper\BusinessHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/business/get', name: 'get_business')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getBusiness(
        BusinessHelper         $businessHelper, ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder,
        EntityManagerInterface $entityManager, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('businessId')) {
            if (!$this->isGranted($delete ? Business::PERMISSION_BUSINESS_DELETE : Business::PERMISSION_BUSINESS_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $business = $businessHelper->getBusiness($request->query->get('businessId'));
        } else {
            if ($delete || !$this->isGranted(Business::PERMISSION_BUSINESS_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $business = null;
        }

        $template = $this->renderView(
            'Business/_partial/body.html.twig',
            [
                'business'            => $business,
                'propertyTypeLocales' => $entityManager->getRepository(PropertyTypeLocale::class)->findBy(
                    ['locale' => $this->getUser()->getLocale()], ['name' => 'ASC']
                ),
                'titleUpdates'        => $entityManager->getRepository(TitleUpdate::class)->findBy([], ['name' => 'ASC'])
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $businessHelper->getDeletion((int)$request->query->get('businessId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

<?php

namespace App\Controller\BusinessGood;

use App\Entity\BusinessGood;
use App\Entity\BusinessLocale;
use App\Helper\BusinessGoodHelper;
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
    #[Route(path: '/business-good/get', name: 'get_business_good')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getBusinessGood(
        BusinessGoodHelper     $businessGoodHelper, ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder,
        EntityManagerInterface $entityManager, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('businessGoodId')) {
            if (!$this->isGranted($delete ? BusinessGood::PERMISSION_BUSINESS_GOOD_DELETE : BusinessGood::PERMISSION_BUSINESS_GOOD_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $businessGood = $businessGoodHelper->getBusinessGood($request->query->get('businessGoodId'));
        } else {
            if ($delete || !$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $businessGood = null;
        }

        $template = $this->renderView(
            'BusinessGood/_partial/body.html.twig',
            [
                'businessGood'    => $businessGood,
                'businessLocales' => $entityManager->getRepository(BusinessLocale::class)->findBy([], ['name' => 'ASC'])
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $businessGoodHelper->getDeletion((int)$request->query->get('businessGoodId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

<?php

namespace App\Controller\BusinessGood;

use App\Entity\BusinessGood;
use App\Helper\BusinessGoodHelper;
use App\Helper\ConfigurationHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/business-good/delete', name: 'delete_business_good', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        BusinessGoodHelper $businessGoodHelper, ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request
    ): Response {
        if (!$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $businessGoodId = (int)$request->request->get('businessGoodId');

        if (!$businessGood = $businessGoodHelper->getBusinessGood($businessGoodId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($businessGoodHelper->getDeletion($businessGoodId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $businessGoodHelper->delete($businessGoodId);

            $filter        = $businessGoodHelper->getFilter();
            $businessGoods = $businessGoodHelper->getBusinessGoods($filter);

            return new JsonResponse(
                [
                    'businessGoods' => $businessGoods,
                    'filter'        => $this->renderView('BusinessGood/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'       => ucfirst($configurationHelper->getTranslateLocale('success_business_good_delete', ['name' => $businessGood['businessName']])),
                    'success'       => true,
                    'template'      => $this->renderView(
                        'BusinessGood/_partial/template.html.twig',
                        [
                            'businessGoods' => $businessGoods,
                            'filter'        => $filter,
                            'totalNames'    => $businessGoodHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_business_good_delete', ['name' => $businessGood['businessName']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

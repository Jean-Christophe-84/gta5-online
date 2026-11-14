<?php

namespace App\Controller\Business;

use App\Entity\Business;
use App\Helper\BusinessHelper;
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
    #[Route(path: '/business/delete', name: 'delete_business', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        BusinessHelper $businessHelper, ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request
    ): Response {
        if (!$this->isGranted(Business::PERMISSION_BUSINESS_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $businessId = (int)$request->request->get('businessId');

        if (!$business = $businessHelper->getBusiness($businessId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($businessHelper->getDeletion($businessId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $businessHelper->delete($businessId);

            $filter     = $businessHelper->getFilter();
            $businesses = $businessHelper->getBusinesses($filter);

            return new JsonResponse(
                [
                    'businesses' => $businesses,
                    'filter'     => $this->renderView(
                        'Business/_partial/filter.html.twig',
                        [
                            'filter'                => $filter,
                            'filterPropertyTypeIds' => $businessHelper->getFilterPropertyTypeIds($filter),
                            'filterTitleUpdateIds'  => $businessHelper->getFilterTitleUpdateIds($filter)
                        ]
                    ),
                    'message'    => ucfirst($configurationHelper->getTranslateLocale('success_business_delete', ['name' => $business['name']])),
                    'success'    => true,
                    'template'   => $this->renderView(
                        'Business/_partial/template.html.twig',
                        [
                            'businesses' => $businesses,
                            'filter'     => $filter,
                            'totalNames' => $businessHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_business_delete', ['name' => $business['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

<?php

namespace App\Controller\Customization;

use App\Entity\Customization;
use App\Helper\ConfigurationHelper;
use App\Helper\CustomizationHelper;
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
    #[Route(path: '/customization/delete', name: 'delete_customization', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, CustomizationHelper $customizationHelper, ErrorResponder $errorResponder, Request $request
    ): Response {
        if (!$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $customizationId = (int)$request->request->get('customizationId');

        if (!$customization = $customizationHelper->getCustomization($customizationId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($customizationHelper->getDeletion($customizationId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $customizationHelper->delete($customizationId);

            $filter         = $customizationHelper->getFilter();
            $customizations = $customizationHelper->getCustomizations($filter);

            return new JsonResponse(
                [
                    'customizations' => $customizations,
                    'filter'         => $this->renderView('Customization/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'        => ucfirst($configurationHelper->getTranslateLocale('success_customization_delete', ['name' => $customization['name']])),
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
            $message = ucfirst($configurationHelper->getTranslateLocale('error_customization_delete', ['name' => $customization['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

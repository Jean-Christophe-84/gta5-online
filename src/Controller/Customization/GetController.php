<?php

namespace App\Controller\Customization;

use App\Entity\Customization;
use App\Helper\ConfigurationHelper;
use App\Helper\CustomizationHelper;
use App\Service\DeletionResponder;
use Exception;
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
     * @throws Exception
     */
    #[Route(path: '/customization/get', name: 'get_customization')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getCustomization(
        ConfigurationHelper $configurationHelper, CustomizationHelper $customizationHelper, DeletionResponder $deletionResponder, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('customizationId')) {
            if (!$this->isGranted($delete ? Customization::PERMISSION_CUSTOMIZATION_DELETE : Customization::PERMISSION_CUSTOMIZATION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $customization = $customizationHelper->getCustomization($request->query->get('customizationId'));
        } else {
            if ($delete || !$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $customization = null;
        }

        $template = $this->renderView('Customization/_partial/body.html.twig', ['customization' => $customization]);

        if ($delete) {
            return $deletionResponder->json($template, $customizationHelper->getDeletion((int)$request->query->get('customizationId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

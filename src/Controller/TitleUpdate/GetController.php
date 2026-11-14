<?php

namespace App\Controller\TitleUpdate;

use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\TitleUpdateHelper;
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
    #[Route(path: '/title-update/get', name: 'get_title-update')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getTitleUpdate(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, Request $request, TitleUpdateHelper $titleUpdateHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('titleUpdateId')) {
            if (!$this->isGranted($delete ? TitleUpdate::PERMISSION_TITLE_UPDATE_DELETE : TitleUpdate::PERMISSION_TITLE_UPDATE_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $titleUpdate = $titleUpdateHelper->getTitleUpdate($request->query->get('titleUpdateId'));
        } else {
            if ($delete || !$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $titleUpdate = null;
        }

        $template = $this->renderView('TitleUpdate/_partial/body.html.twig', ['titleUpdate' => $titleUpdate]);

        if ($delete) {
            return $deletionResponder->json($template, $titleUpdateHelper->getDeletion((int)$request->query->get('titleUpdateId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

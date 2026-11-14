<?php

namespace App\Controller\ModificationLocation;

use App\Entity\ModificationLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\ModificationLocationHelper;
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
    #[Route(path: '/modification-location/get', name: 'get_modification-location')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getModificationLocation(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, ModificationLocationHelper $modificationLocationHelper,
        Request             $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('modificationLocationId')) {
            if (!$this->isGranted($delete ? ModificationLocation::PERMISSION_MODIFICATION_LOCATION_DELETE : ModificationLocation::PERMISSION_MODIFICATION_LOCATION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $modificationLocation = $modificationLocationHelper->getModificationLocation($request->query->get('modificationLocationId'));
        } else {
            if ($delete || !$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $modificationLocation = null;
        }

        $template = $this->renderView('ModificationLocation/_partial/body.html.twig', ['modificationLocation' => $modificationLocation]);

        if ($delete) {
            return $deletionResponder->json($template, $modificationLocationHelper->getDeletion((int)$request->query->get('modificationLocationId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

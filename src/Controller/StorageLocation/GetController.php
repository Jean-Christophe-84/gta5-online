<?php

namespace App\Controller\StorageLocation;

use App\Entity\StorageLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\StorageLocationHelper;
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
    #[Route(path: '/storage-location/get', name: 'get_storage-location')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getStorageLocation(
        ConfigurationHelper   $configurationHelper, DeletionResponder $deletionResponder, Request $request,
        StorageLocationHelper $storageLocationHelper
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('storageLocationId')) {
            if (!$this->isGranted($delete ? StorageLocation::PERMISSION_STORAGE_LOCATION_DELETE : StorageLocation::PERMISSION_STORAGE_LOCATION_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $storageLocation = $storageLocationHelper->getStorageLocation($request->query->get('storageLocationId'));
        } else {
            if ($delete || !$this->isGranted(StorageLocation::PERMISSION_STORAGE_LOCATION_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $storageLocation = null;
        }

        $template = $this->renderView('StorageLocation/_partial/body.html.twig', ['storageLocation' => $storageLocation]);

        if ($delete) {
            return $deletionResponder->json($template, $storageLocationHelper->getDeletion((int)$request->query->get('storageLocationId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

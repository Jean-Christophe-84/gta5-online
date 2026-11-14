<?php

namespace App\Controller\StorageLocation;

use App\Entity\StorageLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\StorageLocationHelper;
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
    #[Route(path: '/storage-location/delete', name: 'delete_storage-location', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, StorageLocationHelper $storageLocationHelper
    ): Response {
        if (!$this->isGranted(StorageLocation::PERMISSION_STORAGE_LOCATION_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $storageLocationId = (int)$request->request->get('storageLocationId');

        if (!$storageLocation = $storageLocationHelper->getStorageLocation($storageLocationId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($storageLocationHelper->getDeletion($storageLocationId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $storageLocationHelper->delete($storageLocationId);

            $filter           = $storageLocationHelper->getFilter();
            $storageLocations = $storageLocationHelper->getStorageLocations($filter);

            return new JsonResponse(
                [
                    'filter'           => $this->renderView('StorageLocation/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'          => ucfirst($configurationHelper->getTranslateLocale('success_storage_location_delete', ['name' => $storageLocation['name']])),
                    'storageLocations' => $storageLocations,
                    'success'          => true,
                    'template'         => $this->renderView(
                        'StorageLocation/_partial/template.html.twig',
                        [
                            'filter'           => $filter,
                            'storageLocations' => $storageLocations,
                            'totalNames'       => $storageLocationHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_storage_location_delete', ['name' => $storageLocation['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

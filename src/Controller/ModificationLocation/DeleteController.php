<?php

namespace App\Controller\ModificationLocation;

use App\Entity\ModificationLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\ModificationLocationHelper;
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
    #[Route(path: '/modification-location/delete', name: 'delete_modification-location', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, ModificationLocationHelper $modificationLocationHelper, Request $request
    ): Response {
        if (!$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $modificationLocationId = (int)$request->request->get('modificationLocationId');

        if (!$modificationLocation = $modificationLocationHelper->getModificationLocation($modificationLocationId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($modificationLocationHelper->getDeletion($modificationLocationId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $modificationLocationHelper->delete($modificationLocationId);

            $filter         = $modificationLocationHelper->getFilter();
            $modificationLocations = $modificationLocationHelper->getModificationLocations($filter);

            return new JsonResponse(
                [
                    'filter'                => $this->renderView('ModificationLocation/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'               => ucfirst($configurationHelper->getTranslateLocale('success_modification_location_delete', ['name' => $modificationLocation['name']])),
                    'modificationLocations' => $modificationLocations,
                    'success'               => true,
                    'template'              => $this->renderView(
                        'ModificationLocation/_partial/template.html.twig',
                        [
                            'filter'                => $filter,
                            'modificationLocations' => $modificationLocations,
                            'totalNames'            => $modificationLocationHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_modification_location_delete', ['name' => $modificationLocation['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

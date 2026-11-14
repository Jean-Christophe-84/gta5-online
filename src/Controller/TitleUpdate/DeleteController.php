<?php

namespace App\Controller\TitleUpdate;

use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\TitleUpdateHelper;
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
    #[Route(path: '/title-update/delete', name: 'delete_title-update', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, TitleUpdateHelper $titleUpdateHelper
    ): Response {
        if (!$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $titleUpdateId = (int)$request->request->get('titleUpdateId');

        if (!$titleUpdate = $titleUpdateHelper->getTitleUpdate($titleUpdateId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($titleUpdateHelper->getDeletion($titleUpdateId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $titleUpdateHelper->delete($titleUpdateId);

            $filter = $titleUpdateHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'       => $this->renderView('TitleUpdate/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'      => ucfirst($configurationHelper->getTranslateLocale('success_title_update_delete', ['name' => $titleUpdate['name']])),
                    'success'      => true,
                    'template'     => $this->renderView(
                        'TitleUpdate/_partial/template.html.twig',
                        [
                            'filter'       => $filter,
                            'titleUpdates' => $titleUpdateHelper->getTitleUpdates($filter),
                            'totalNames'   => $titleUpdateHelper->getTotalNames()
                        ]
                    ),
                    'titleUpdates' => $titleUpdateHelper->getTitleUpdates($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_title_update_delete', ['name' => $titleUpdate['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

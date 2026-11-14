<?php

namespace App\Controller\TitleUpdate;

use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\TitleUpdateHelper;
use App\Service\ErrorResponder;
use DateTime;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/title-update/form', name: 'form_title-update', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        TitleUpdateHelper   $titleUpdateHelper
    ): Response {
        $type = $request->request->get('titleUpdateId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'name'        => 'please_enter_the_name',
                'releaseDate' => 'please_enter_the_release_date'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if (!DateTime::createFromFormat('Y-m-d', (string)$request->request->get('releaseDate'))) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('please_enter_the_release_date')), 'success' => false]
            );
        }

        try {
            if ($type === 'update') {
                if (!$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $titleUpdate = $entityManager->getRepository(TitleUpdate::class)->find($request->request->get('titleUpdateId'));
            } else {
                if (!$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $titleUpdate = new TitleUpdate();
            }
            $titleUpdate->setName(trim($request->request->get('name')));
            $titleUpdate->setReleaseDate(new DateTime($request->request->get('releaseDate')));

            $entityManager->persist($titleUpdate);
            $entityManager->flush();

            $filter = $titleUpdateHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'       => $this->renderView('TitleUpdate/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'      => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_title_update_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
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
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_title_update_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

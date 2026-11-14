<?php

namespace App\Controller\GroupGta;

use App\Entity\GroupGta;
use App\Entity\GroupGtaLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\GroupGtaHelper;
use App\Service\ErrorResponder;
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
    #[Route(path: '/group-gta/form', name: 'form_group-gta', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function edit(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        GroupGtaHelper      $groupGtaHelper
    ): Response {
        $type = $request->request->get('groupGtaId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'name'        => 'please_enter_the_name',
                'description' => 'please_enter_the_description'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                $groupGta = $entityManager->getRepository(GroupGta::class)->find($request->request->get('groupGtaId'));
            } else {
                $groupGta = new GroupGta();
            }

            if ($request->request->get('default')) {
                foreach ($entityManager->getRepository(GroupGta::class)->findBy(['default' => true]) as $defaultGroupGta) {
                    $defaultGroupGta->setDefault(false);
                }

                $groupGta->setDefault(true);
            } else if ($groupGta->getDefault()) {
                return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_default_group_is_required')), 'success' => false]);
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $groupGtaLocale = $entityManager->getRepository(GroupGtaLocale::class)->findOneBy(['groupGta' => $groupGta, 'locale' => $locale]);

            if (!$groupGtaLocale instanceof GroupGtaLocale) {
                $groupGtaLocale = new GroupGtaLocale();
                $groupGtaLocale->setGroupGta($groupGta);
                $groupGtaLocale->setLocale($locale);
            }

            $groupGtaLocale->setName(trim($request->request->get('name')));
            $groupGtaLocale->setDescription(trim($request->request->get('description')));

            $entityManager->persist($groupGtaLocale);
            $entityManager->persist($groupGta);
            $entityManager->flush();

            $filter    = $groupGtaHelper->getFilter();
            $groupGtas = $groupGtaHelper->getGroupGtas($filter);

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('GroupGta/_partial/filter.html.twig', ['filter' => $filter]),
                    'groupGtas' => $groupGtas,
                    'message'   => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_group_gta_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'   => true,
                    'template'  => $this->renderView(
                        'GroupGta/_partial/template.html.twig',
                        [
                            'filter'     => $filter,
                            'groupGtas'  => $groupGtas,
                            'totalNames' => $groupGtaHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_group_gta_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

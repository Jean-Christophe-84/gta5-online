<?php

namespace App\Controller\BountyTarget;

use App\Entity\BountyMission;
use App\Entity\BountyTarget;
use App\Entity\BountyTargetUser;
use App\Entity\User;
use App\Helper\BountyTargetHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/bounty-target/form', name: 'form_bounty-target', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        BountyTargetHelper $bountyTargetHelper, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        Request            $request
    ): Response {
        $type = $request->request->get('bountyTargetId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, ['name' => 'please_enter_the_name']
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($type === 'update') {
                if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $bountyTarget = $entityManager->getRepository(BountyTarget::class)->find($request->request->get('bountyTargetId'));
            } else {
                if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $bountyTarget = new BountyTarget();
            }

            if ($request->request->get('bountyMissionId')) {
                $bountyMission = $entityManager->getRepository(BountyMission::class)->find($request->request->get('bountyMissionId'));
            } else {
                $bountyMission = null;
            }

            $bountyTarget->setName(trim($request->request->get('name')));
            $bountyTarget->setBountyMission($bountyMission);
            $bountyTarget->setMostWanted((bool)$request->request->get('mostWanted'));

            $entityManager->persist($bountyTarget);
            $entityManager->flush();

            if ($this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_DONE)) {
                /** @var User $user */
                $user = $this->getUser();

                $bountyTargetUser = $entityManager->getRepository(BountyTargetUser::class)->findOneBy(['user' => $user, 'bountyTarget' => $bountyTarget]);

                if (!$bountyTargetUser instanceof BountyTargetUser) {
                    $bountyTargetUser = new BountyTargetUser();
                    $bountyTargetUser->setUser($user);
                    $bountyTargetUser->setBountyTarget($bountyTarget);
                }

                $bountyTargetUser->setDone((bool)$request->request->get('done'));

                $entityManager->persist($bountyTargetUser);
                $entityManager->flush();
            }

            $parameters = $bountyTargetHelper->getParameters($bountyTargetHelper->getFilter());

            return new JsonResponse(
                [
                    'bountyTargets' => $parameters['bountyTargets'],
                    'filter'        => $this->renderView('BountyTarget/_partial/filter.html.twig', $parameters),
                    'message'       => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_bounty_target_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'       => true,
                    'summary'       => $this->renderView('_partial/summary.html.twig', ['entity' => 'bounty_targets', 'summary' => $parameters['summary']]),
                    'template'      => $this->renderView('BountyTarget/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_bounty_target_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

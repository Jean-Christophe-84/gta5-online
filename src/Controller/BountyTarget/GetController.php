<?php

declare(strict_types=1);

namespace App\Controller\BountyTarget;

use App\Entity\BountyMissionLocale;
use App\Entity\BountyTarget;
use App\Entity\User;
use App\Helper\BountyTargetHelper;
use App\Helper\ConfigurationHelper;
use App\Service\DeletionResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
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
     * @throws DoctrineDBALException
     */
    #[Route(path: '/bounty-target/get', name: 'get_bounty-target')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getBountyTarget(
        BountyTargetHelper     $bountyTargetHelper, ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder,
        EntityManagerInterface $entityManager, Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('bountyTargetId')) {
            if (!$this->isGranted($delete ? BountyTarget::PERMISSION_BOUNTY_TARGET_DELETE : BountyTarget::PERMISSION_BOUNTY_TARGET_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $bountyTarget = $bountyTargetHelper->getBountyTarget((int)$request->query->get('bountyTargetId'));
        } else {
            if ($delete || !$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $bountyTarget = null;
        }

        $template = $this->renderView(
            'BountyTarget/_partial/body.html.twig',
            [
                'bountyMissionLocales' => $entityManager->getRepository(BountyMissionLocale::class)->findBy(
                    ['locale' => $user->getLocale()], ['name' => 'ASC']
                ),
                'bountyTarget'         => $bountyTarget
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $bountyTargetHelper->getDeletion((int)$request->query->get('bountyTargetId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

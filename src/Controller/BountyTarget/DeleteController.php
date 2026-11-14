<?php

namespace App\Controller\BountyTarget;

use App\Entity\BountyTarget;
use App\Helper\BountyTargetHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/bounty-target/delete', name: 'delete_bounty-target', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        BountyTargetHelper $bountyTargetHelper, ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request
    ): Response {
        if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $bountyTargetId = (int)$request->request->get('bountyTargetId');

        if (!$bountyTarget = $bountyTargetHelper->getBountyTarget($bountyTargetId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($bountyTargetHelper->getDeletion($bountyTargetId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $bountyTargetHelper->delete($bountyTargetId);

            $parameters = $bountyTargetHelper->getParameters($bountyTargetHelper->getFilter());

            return new JsonResponse(
                [
                    'bountyTargets' => $parameters['bountyTargets'],
                    'filter'        => $this->renderView('BountyTarget/_partial/filter.html.twig', $parameters),
                    'message'       => ucfirst($configurationHelper->getTranslateLocale('success_bounty_target_delete', ['name' => $bountyTarget['name']])),
                    'success'       => true,
                    'summary'       => $this->renderView('_partial/summary.html.twig', ['entity' => 'bounty_targets', 'summary' => $parameters['summary']]),
                    'template'      => $this->renderView('BountyTarget/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_bounty_target_delete', ['name' => $bountyTarget['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

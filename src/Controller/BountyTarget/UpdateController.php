<?php

namespace App\Controller\BountyTarget;

use App\Entity\BountyTarget;
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

class UpdateController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/bounty-target/update', name: 'update_bounty-target', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function update(
        BountyTargetHelper $bountyTargetHelper, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        Request            $request
    ): Response {
        if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_DONE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        if (!$bountyTarget = $bountyTargetHelper->getBountyTarget((int)$request->request->get('bountyTargetId'))) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $sql        = "
                INSERT INTO bounty_target_user (user_id, bounty_target_id, done, created_at, created_by_id, updated_at, updated_by_id)
                VALUES (:userId, :bountyTargetId, :done, :updatedAt, :userId, :updatedAt, :userId)
                ON DUPLICATE KEY UPDATE done = :done, updated_at = :updatedAt, updated_by_id = :userId
            ";
            $parameters = [
                'bountyTargetId' => $bountyTarget['id'],
                'done'           => (int)(bool)$request->request->get('done'),
                'updatedAt'      => date('Y-m-d H:i:s'),
                'userId'         => $user->id
            ];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            $parameters = $bountyTargetHelper->getParameters($bountyTargetHelper->getFilter());

            return new JsonResponse(
                [
                    'bountyTargets' => $parameters['bountyTargets'],
                    'filter'        => $this->renderView('BountyTarget/_partial/filter.html.twig', $parameters),
                    'message'       => ucfirst($configurationHelper->getTranslateLocale('success_bounty_target_list', ['name' => $bountyTarget['name']])),
                    'success'       => true,
                    'summary'       => $this->renderView('_partial/summary.html.twig', ['entity' => 'bounty_targets', 'summary' => $parameters['summary']]),
                    'template'      => $this->renderView('BountyTarget/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_bounty_target_list', ['name' => $bountyTarget['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}

<?php

namespace App\Controller\Heist\KortzCenter;

use App\Entity\HeistKortzCenter;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistKortzCenterHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ResetController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/kortz-center/reset', name: 'reset_kortz_center_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistKortzCenterHelper $heistKortzCenterHelper
    ): JsonResponse {
        if (!$this->isGranted(HeistKortzCenter::PERMISSION_HEIST_KORTZ_CENTER_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            $sql = "
                UPDATE heist_kortz_center
                SET heist_kortz_center.first_time_in_week = NULL, heist_kortz_center.difficulty = NULL, heist_kortz_center.material = NULL,
                    heist_kortz_center.weapon = NULL, heist_kortz_center.vehicle = NULL, heist_kortz_center.main_loot_id = NULL,
                    heist_kortz_center.updated_at = :updatedAt, heist_kortz_center.updated_by_id = :userId
                WHERE heist_kortz_center.user_id = :userId
            ";

            $entityManager->getConnection()->executeStatement($sql, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id]);

            $sql = "
                UPDATE heist_kortz_center_heist_kortz_center_secondary_loot
                SET heist_kortz_center_heist_kortz_center_secondary_loot.active = '0', heist_kortz_center_heist_kortz_center_secondary_loot.buyer_request = '0',
                    heist_kortz_center_heist_kortz_center_secondary_loot.updated_at = :updatedAt, heist_kortz_center_heist_kortz_center_secondary_loot.updated_by_id = :userId
                WHERE heist_kortz_center_heist_kortz_center_secondary_loot.user_id = :userId
            ";

            $entityManager->getConnection()->executeStatement($sql, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id]);

            $heistKortzCenter = $entityManager->getRepository(HeistKortzCenter::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'kortz_center', 'type' => 'reset'])),
                    'success'  => true,
                    'template' => $this->renderView(
                        'Heist/KortzCenter/_partial/loots.html.twig',
                        [
                            'difficulties'                   => $heistKortzCenterHelper->getDifficulties(),
                            'heistKortzCenter'               => $heistKortzCenter,
                            'heistKortzCenterMainLoots'      => $heistKortzCenterHelper->getHeistKortzCenterMainLoots(),
                            'heistKortzCenterSecondaryLoots' => $heistKortzCenterHelper->getHeistKortzCenterSecondaryLoots($user->id),
                            'materials'                      => $heistKortzCenterHelper->getMaterials(),
                            'optimizeProfitsForOnePlayer'    => $heistKortzCenterHelper->optimizeProfits($heistKortzCenter, 1),
                            'optimizeProfitsForTwoPlayers'   => $heistKortzCenterHelper->optimizeProfits($heistKortzCenter, 2),
                            'optimizeProfitsForThreePlayers' => $heistKortzCenterHelper->optimizeProfits($heistKortzCenter, 3),
                            'optimizeProfitsForFourPlayers'  => $heistKortzCenterHelper->optimizeProfits($heistKortzCenter, 4),
                            'vehicles'                       => $heistKortzCenterHelper->getVehicles(),
                            'weapons'                        => $heistKortzCenterHelper->getWeapons()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'kortz_center', 'type' => 'reset']));

            return $errorResponder->json($exception, $message);
        }
    }
}

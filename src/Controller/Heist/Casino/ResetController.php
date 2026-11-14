<?php

namespace App\Controller\Heist\Casino;

use App\Entity\HeistCasino;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistCasinoHelper;
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
    #[Route(path: '/heist/casino/reset', name: 'reset_casino_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistCasinoHelper   $heistCasinoHelper
    ): JsonResponse {
        if (!$this->isGranted(HeistCasino::PERMISSION_HEIST_CASINO_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            $sql = "
                UPDATE heist_casino
                SET heist_casino.first_time_in_week = NULL, heist_casino.main_loot_id = NULL, heist_casino.difficulty = NULL, heist_casino.approach = NULL,
                    heist_casino.hacker_id = NULL, heist_casino.driver_id = NULL, heist_casino.gunman_id = NULL, heist_casino.updated_at = :updatedAt,
                    heist_casino.updated_by_id = :userId
                WHERE heist_casino.user_id = :userId
            ";

            $entityManager->getConnection()->executeStatement($sql, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id]);

            $heistCasino = $entityManager->getRepository(HeistCasino::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'casino', 'type' => 'reset'])),
                    'success'  => true,
                    'template' => $this->renderView(
                        'Heist/Casino/_partial/loots.html.twig',
                        [
                            'approaches'           => $heistCasinoHelper->getApproaches(),
                            'difficulties'         => $heistCasinoHelper->getDifficulties(),
                            'heistCasino'          => $heistCasino,
                            'heistCasinoDrivers'   => $heistCasinoHelper->getHeistCasinoDrivers(),
                            'heistCasinoHackers'   => $heistCasinoHelper->getHeistCasinoHackers(),
                            'heistCasinoMainLoots' => $heistCasinoHelper->getHeistCasinoMainLoots(),
                            'heistCasinoGunmen'    => $heistCasinoHelper->getHeistCasinoGunmen(),
                            'optimizeProfits'      => $heistCasinoHelper->optimizeProfits($heistCasino)
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'casino', 'type' => 'reset']));

            return $errorResponder->json($exception, $message);
        }
    }
}

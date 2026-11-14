<?php

namespace App\Controller\Heist\CayoPerico;

use App\Entity\HeistCayoPerico;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistCayoPericoHelper;
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
    #[Route(path: '/heist/cayo-perico/reset', name: 'reset_cayo_perico_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistCayoPericoHelper $heistCayoPericoHelper
    ): JsonResponse {
        if (!$this->isGranted(HeistCayoPerico::PERMISSION_HEIST_CAYO_PERICO_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            $sql = "
                UPDATE heist_cayo_perico
                SET heist_cayo_perico.difficulty = NULL, heist_cayo_perico.cocaine_zone_one = 0, heist_cayo_perico.weed_zone_one = 0,
                    heist_cayo_perico.cash_zone_one = 0, heist_cayo_perico.cocaine_zone_three = 0, heist_cayo_perico.weed_zone_three = 0,
                    heist_cayo_perico.cash_zone_three = 0, heist_cayo_perico.cocaine_zone_six = 0, heist_cayo_perico.weed_zone_six = 0,
                    heist_cayo_perico.cash_zone_six = 0, heist_cayo_perico.main_loot_id = NULL, heist_cayo_perico.gold_main_basement = 0,
                    heist_cayo_perico.cocaine_main_basement = 0, heist_cayo_perico.weed_main_basement = 0, heist_cayo_perico.artwork_main_basement = 0,
                    heist_cayo_perico.cash_main_basement = 0, heist_cayo_perico.artwork_main_desktop = 0, heist_cayo_perico.gold_main_west = 0,
                    heist_cayo_perico.cocaine_main_west = 0, heist_cayo_perico.weed_main_west = 0, heist_cayo_perico.artwork_main_west = 0,
                    heist_cayo_perico.cash_main_west = 0, heist_cayo_perico.gold_main_north = 0, heist_cayo_perico.cocaine_main_north = 0,
                    heist_cayo_perico.weed_main_north = 0, heist_cayo_perico.artwork_main_north = 0, heist_cayo_perico.cash_main_north = 0,
                    heist_cayo_perico.gold_main_south = 0, heist_cayo_perico.cocaine_main_south = 0, heist_cayo_perico.weed_main_south = 0,
                    heist_cayo_perico.artwork_main_south = 0, heist_cayo_perico.cash_main_south = 0,
                    heist_cayo_perico.updated_at = :updatedAt, heist_cayo_perico.updated_by_id = :userId
                WHERE heist_cayo_perico.user_id = :userId
            ";

            $entityManager->getConnection()->executeStatement($sql, ['updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id]);

            $heistCayoPerico = $entityManager->getRepository(HeistCayoPerico::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message' => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'cayo_perico', 'type' => 'reset'])),
                    'success' => true,
                    'template' => $this->renderView(
                        'Heist/CayoPerico/_partial/loots.html.twig',
                        [
                            'difficulties'                   => $heistCayoPericoHelper->getDifficulties(),
                            'heistCayoPerico'                => $heistCayoPerico,
                            'heistCayoPericoMainLoots'       => $heistCayoPericoHelper->getHeistCayoPericoMainLoots(),
                            'heistCayoPericoSecondaryLoots'  => $heistCayoPericoHelper->getHeistCayoPericoSecondaryLoots(),
                            'optimizeProfitsForOnePlayer'    => $heistCayoPericoHelper->optimizeProfits($heistCayoPerico, 1),
                            'optimizeProfitsForTwoPlayers'   => $heistCayoPericoHelper->optimizeProfits($heistCayoPerico, 2),
                            'optimizeProfitsForThreePlayers' => $heistCayoPericoHelper->optimizeProfits($heistCayoPerico, 3),
                            'optimizeProfitsForFourPlayers'  => $heistCayoPericoHelper->optimizeProfits($heistCayoPerico, 4)
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'cayo_perico', 'type' => 'reset']));

            return $errorResponder->json($exception, $message);
        }
    }
}

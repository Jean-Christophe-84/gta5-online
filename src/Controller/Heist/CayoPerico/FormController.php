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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    private const array PLACES = [
        'difficulty', 'main_loot_id', 'artwork_main_basement', 'artwork_main_desktop', 'artwork_main_north', 'artwork_main_south',
        'artwork_main_west', 'cash_main_basement', 'cash_main_north', 'cash_main_south', 'cash_main_west', 'cash_zone_one', 'cash_zone_six',
        'cash_zone_three', 'cocaine_main_basement', 'cocaine_main_north', 'cocaine_main_south', 'cocaine_main_west', 'cocaine_zone_one',
        'cocaine_zone_six', 'cocaine_zone_three', 'gold_main_basement', 'gold_main_north', 'gold_main_south', 'gold_main_west', 'weed_main_basement',
        'weed_main_north', 'weed_main_south', 'weed_main_west', 'weed_zone_one', 'weed_zone_six', 'weed_zone_three'
    ];

    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/cayo-perico/form', name: 'form_cayo_perico_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistCayoPericoHelper $heistCayoPericoHelper, Request $request
    ): JsonResponse {
        if (!$this->isGranted(HeistCayoPerico::PERMISSION_HEIST_CAYO_PERICO_EDIT)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            $place = $request->request->get('place');

            if (!in_array($place, self::PLACES, true)) {
                throw new BadRequestHttpException('Unknown place "' . $place . '"');
            }

            $value = $request->request->get('value');

            if (($place === 'difficulty' || $place === 'main_loot_id') && $value === '0') {
                $value = null;
            }

            if (!$heistCayoPericoHelper->isValidValue($place, $value)) {
                throw new BadRequestHttpException('Invalid value "' . $value . '" for "' . $place . '"');
            }

            $sql        = "
                UPDATE heist_cayo_perico
                SET heist_cayo_perico." . $place . " = :value, heist_cayo_perico.updated_at = :updatedAt, heist_cayo_perico.updated_by_id = :userId
                WHERE heist_cayo_perico.user_id = :userId
            ";
            $parameters = ['value' => $value, 'updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            $heistCayoPerico = $entityManager->getRepository(HeistCayoPerico::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'cayo_perico', 'type' => 'update'])),
                    'success'  => true,
                    'template' => $this->renderView(
                        'Heist/CayoPerico/_partial/loots.html.twig',
                        [
                            'difficulties'                   => $heistCayoPericoHelper->getDifficulties(),
                            'heistCayoPerico'                => $entityManager->getRepository(HeistCayoPerico::class)->findOneBy(['user' => $user]),
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
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'cayo_perico', 'type' => 'update']));

            return $errorResponder->json($exception, $message);
        }
    }
}

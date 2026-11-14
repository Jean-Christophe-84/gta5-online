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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    private const array PLACES = [
        'difficulty', 'first_time_in_week', 'main_loot_id', 'material', 'vehicle', 'weapon'
    ];

    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/kortz-center/form', name: 'form_kortz_center_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistKortzCenterHelper $heistKortzCenterHelper, Request $request
    ): JsonResponse {
        if (!$this->isGranted(HeistKortzCenter::PERMISSION_HEIST_KORTZ_CENTER_EDIT)) {
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

            if (($place !== 'first_time_in_week' && $value === '0') || ($place === 'first_time_in_week' && $value === 'null')) {
                $value = null;
            }

            if (!$heistKortzCenterHelper->isValidValue($place, $value)) {
                throw new BadRequestHttpException('Invalid value "' . $value . '" for "' . $place . '"');
            }

            $sql        = "
                UPDATE heist_kortz_center
                SET heist_kortz_center." . $place . " = :value, heist_kortz_center.updated_at = :updatedAt, heist_kortz_center.updated_by_id = :userId
                WHERE heist_kortz_center.user_id = :userId
            ";
            $parameters = ['value' => $value, 'updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            $heistKortzCenter = $entityManager->getRepository(HeistKortzCenter::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'kortz_center', 'type' => 'update'])),
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
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'kortz_center', 'type' => 'update']));

            return $errorResponder->json($exception, $message);
        }
    }
}

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

class FormSecondaryLootController extends AbstractController
{
    private const array TYPES = [
        'active', 'buyer_request', 'heist_kortz_center_secondary_loot_name_id', 'price'
    ];

    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/kortz-center/form-secondary-loot', name: 'form_secondary_loot_kortz_center_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function formSecondaryLoot(
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

            $type = $request->request->get('type');

            if (!in_array($type, self::TYPES, true)) {
                throw new BadRequestHttpException('Unknown type "' . $type . '"');
            }

            if ($type === 'active' || $type === 'buyer_request') {
                $value = $request->request->get('value');
            } else {
                $value = $request->request->get('value') ? : null;
            }

            if (!$heistKortzCenterHelper->isValidSecondaryLootValue($type, $value, (int)$request->request->get('id'))) {
                throw new BadRequestHttpException('Invalid value "' . $value . '" for "' . $type . '"');
            }

            $sql        = "
                UPDATE heist_kortz_center_heist_kortz_center_secondary_loot
                INNER JOIN heist_kortz_center_secondary_loot_name
                    ON heist_kortz_center_heist_kortz_center_secondary_loot.heist_kortz_center_secondary_loot_name_id = heist_kortz_center_secondary_loot_name.id
                SET heist_kortz_center_heist_kortz_center_secondary_loot." . $type . " = :value, heist_kortz_center_heist_kortz_center_secondary_loot.updated_at = :updatedAt,
                    heist_kortz_center_heist_kortz_center_secondary_loot.updated_by_id = :userId
                WHERE heist_kortz_center_secondary_loot_name.heist_kortz_center_secondary_loot_id = :id
                AND heist_kortz_center_heist_kortz_center_secondary_loot.user_id = :userId
            ";
            $parameters = ['value' => $value, 'updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id, 'id' => $request->request->get('id')];

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

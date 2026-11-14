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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    private const array PLACES = [
        'approach', 'difficulty', 'driver_id', 'first_time_in_week', 'gunman_id', 'hacker_id', 'main_loot_id'
    ];

    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/casino/form', name: 'form_casino_heist', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        HeistCasinoHelper   $heistCasinoHelper, Request $request
    ): JsonResponse {
        if (!$this->isGranted(HeistCasino::PERMISSION_HEIST_CASINO_EDIT)) {
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

            if (!$heistCasinoHelper->isValidValue($place, $value)) {
                throw new BadRequestHttpException('Invalid value "' . $value . '" for "' . $place . '"');
            }

            $sql        = "
                UPDATE heist_casino
                SET heist_casino." . $place . " = :value, heist_casino.updated_at = :updatedAt, heist_casino.updated_by_id = :userId
                WHERE heist_casino.user_id = :userId
            ";
            $parameters = ['value' => $value, 'updatedAt' => date('Y-m-d H:i:s'), 'userId' => $user->id];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            $heistCasino = $entityManager->getRepository(HeistCasino::class)->findOneBy(['user' => $user]);

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('success_heist', ['heist' => 'casino', 'type' => 'update'])),
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
            $message = ucfirst($configurationHelper->getTranslateLocale('error_heist', ['heist' => 'casino', 'type' => 'update']));

            return $errorResponder->json($exception, $message);
        }
    }
}

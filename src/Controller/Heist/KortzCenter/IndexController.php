<?php

namespace App\Controller\Heist\KortzCenter;

use App\Entity\HeistKortzCenter;
use App\Entity\HeistKortzCenterHeistKortzCenterSecondaryLoot;
use App\Entity\HeistKortzCenterSecondaryLoot;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistKortzCenterHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/heist/kortz-center', name: 'kortz_center_heist')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, HeistKortzCenterHelper $heistKortzCenterHelper
    ): Response {
        if (!$this->isGranted(HeistKortzCenter::PERMISSION_HEIST_KORTZ_CENTER_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!($heistKortzCenter = $entityManager->getRepository(HeistKortzCenter::class)->findOneBy(['user' => $user])) instanceof HeistKortzCenter) {
            $heistKortzCenter = new HeistKortzCenter();
            $heistKortzCenter->setUser($user);

            $entityManager->persist($heistKortzCenter);
            $entityManager->flush();
        }

        if ($heistKortzCenterSecondaryLoots = $entityManager->getRepository(HeistKortzCenterSecondaryLoot::class)->findAll()) {
            foreach ($heistKortzCenterSecondaryLoots as $heistKortzCenterSecondaryLoot) {
                // @note: the user keeps one row per secondary loot, whichever name is chosen
                $userSecondaryLoot = $entityManager->getRepository(HeistKortzCenterHeistKortzCenterSecondaryLoot::class)->findOneBy(
                    ['user' => $user, 'heistKortzCenterSecondaryLootName' => $heistKortzCenterSecondaryLoot->heistKortzCenterSecondaryLootNames->toArray()]
                );

                if (!$userSecondaryLoot instanceof HeistKortzCenterHeistKortzCenterSecondaryLoot) {
                    $heistKortzCenterHeistKortzCenterSecondaryLoot = new HeistKortzCenterHeistKortzCenterSecondaryLoot();
                    $heistKortzCenterHeistKortzCenterSecondaryLoot->setUser($user);
                    $heistKortzCenterHeistKortzCenterSecondaryLoot->setHeistKortzCenterSecondaryLootName($heistKortzCenterSecondaryLoot->heistKortzCenterSecondaryLootNames[0]);

                    $entityManager->persist($heistKortzCenterHeistKortzCenterSecondaryLoot);
                }
            }

            $entityManager->flush();
        }

        return $this->render(
            'Heist/KortzCenter/index.html.twig',
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
        );
    }
}

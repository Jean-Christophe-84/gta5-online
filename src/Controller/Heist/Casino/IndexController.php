<?php

namespace App\Controller\Heist\Casino;

use App\Entity\HeistCasino;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistCasinoHelper;
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
    #[Route(path: '/heist/casino', name: 'casino_heist')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, HeistCasinoHelper $heistCasinoHelper
    ): Response {
        if (!$this->isGranted(HeistCasino::PERMISSION_HEIST_CASINO_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!($heistCasino = $entityManager->getRepository(HeistCasino::class)->findOneBy(['user' => $user])) instanceof HeistCasino) {
            $heistCasino = new HeistCasino();
            $heistCasino->setUser($user);

            $entityManager->persist($heistCasino);
            $entityManager->flush();
        }

        return $this->render(
            'Heist/Casino/index.html.twig',
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
        );
    }
}

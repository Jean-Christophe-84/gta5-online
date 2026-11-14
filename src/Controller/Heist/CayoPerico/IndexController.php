<?php

namespace App\Controller\Heist\CayoPerico;

use App\Entity\HeistCayoPerico;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\HeistCayoPericoHelper;
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
    #[Route(path: '/heist/cayo-perico', name: 'cayo_perico_heist')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, HeistCayoPericoHelper $heistCayoPericoHelper
    ): Response {
        if (!$this->isGranted(HeistCayoPerico::PERMISSION_HEIST_CAYO_PERICO_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!($heistCayoPerico = $entityManager->getRepository(HeistCayoPerico::class)->findOneBy(['user' => $user])) instanceof HeistCayoPerico) {
            $heistCayoPerico = new HeistCayoPerico();
            $heistCayoPerico->setUser($user);

            $entityManager->persist($heistCayoPerico);
            $entityManager->flush();
        }

        return $this->render(
            'Heist/CayoPerico/index.html.twig',
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
        );
    }
}

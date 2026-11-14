<?php

namespace App\Controller\BountyTarget;

use App\Entity\BountyTarget;
use App\Entity\User;
use App\Helper\BountyTargetHelper;
use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/bounty-targets', name: 'bounty-targets')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(BountyTargetHelper $bountyTargetHelper, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        $query = "
            INSERT INTO bounty_target_user (user_id, bounty_target_id, created_at, created_by_id, updated_at, updated_by_id)
            SELECT :userId, bounty_target.id, :createdAt, :userId, :createdAt, :userId FROM bounty_target
            WHERE NOT EXISTS (SELECT 1 FROM bounty_target_user WHERE user_id = :userId AND bounty_target_id = bounty_target.id)
        ";

        $statement = $entityManager->getConnection()->prepare($query);
        $statement->bindValue('createdAt', date('Y-m-d H:i:s'));
        $statement->bindValue('userId', $user->id);
        $statement->executeStatement();

        return $this->render('BountyTarget/index.html.twig', $bountyTargetHelper->getParameters($bountyTargetHelper->getFilter()));
    }
}

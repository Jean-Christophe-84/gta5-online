<?php

namespace App\Controller\Weapon;

use App\Entity\Configuration;
use App\Entity\User;
use App\Entity\Weapon;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
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
    #[Route(path: '/weapons', name: 'weapons')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, WeaponHelper $weaponHelper): Response
    {
        if (!$this->isGranted(Weapon::PERMISSION_WEAPON_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        $query = "
            INSERT INTO weapon_user (user_id, weapon_id, created_at, created_by_id, updated_at, updated_by_id)
            SELECT :userId, weapon.id, :createdAt, :userId, :createdAt, :userId FROM weapon
            WHERE NOT EXISTS (SELECT 1 FROM weapon_user WHERE user_id = :userId AND weapon_id = weapon.id)
        ";

        $statement = $entityManager->getConnection()->prepare($query);
        $statement->bindValue('createdAt', date('Y-m-d H:i:s'));
        $statement->bindValue('userId', $user->id);
        $statement->executeStatement();

        $filter = $weaponHelper->getFilter();

        return $this->render(
            'Weapon/index.html.twig',
            array_merge(['configuration' => $entityManager->getRepository(Configuration::class)->find(1)], $weaponHelper->getParameters($filter))
        );
    }
}

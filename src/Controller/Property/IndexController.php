<?php

namespace App\Controller\Property;

use App\Entity\Configuration;
use App\Entity\Property;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
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
    #[Route(path: '/properties', name: 'properties')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, PropertyHelper $propertyHelper): Response
    {
        if (!$this->isGranted(Property::PERMISSION_PROPERTY_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        $query = "
            INSERT INTO property_user (user_id, property_id, created_at, created_by_id, updated_at, updated_by_id)
            SELECT :userId, property.id, :createdAt, :userId, :createdAt, :userId FROM property
            WHERE NOT EXISTS (SELECT 1 FROM property_user WHERE user_id = :userId AND property_id = property.id)
        ";

        $statement = $entityManager->getConnection()->prepare($query);
        $statement->bindValue('createdAt', date('Y-m-d H:i:s'));
        $statement->bindValue('userId', $user->id);
        $statement->executeStatement();

        $filter = $propertyHelper->getFilter();

        return $this->render(
            'Property/index.html.twig',
            array_merge(['configuration' => $entityManager->getRepository(Configuration::class)->find(1)], $propertyHelper->getParameters($filter))
        );
    }
}

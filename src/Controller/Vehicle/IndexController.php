<?php

namespace App\Controller\Vehicle;

use App\Entity\Configuration;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
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
    #[Route(path: '/vehicles', name: 'vehicles')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, VehicleHelper $vehicleHelper): Response
    {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        /** @var User $user */
        $user = $this->getUser();

        $query = "
            INSERT INTO vehicle_user (user_id, vehicle_id, created_at, created_by_id, updated_at, updated_by_id)
            SELECT :userId, vehicle.id, :createdAt, :userId, :createdAt, :userId FROM vehicle
            WHERE NOT EXISTS (SELECT 1 FROM vehicle_user WHERE user_id = :userId AND vehicle_id = vehicle.id)
        ";

        $statement = $entityManager->getConnection()->prepare($query);
        $statement->bindValue('createdAt', date('Y-m-d H:i:s'));
        $statement->bindValue('userId', $user->id);
        $statement->executeStatement();

        $filter = $vehicleHelper->getFilter();

        return $this->render(
            'Vehicle/index.html.twig',
            array_merge(['configuration' => $entityManager->getRepository(Configuration::class)->find(1)], $vehicleHelper->getParameters($filter))
        );
    }
}

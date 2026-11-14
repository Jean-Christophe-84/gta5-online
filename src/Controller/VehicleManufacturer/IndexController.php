<?php

namespace App\Controller\VehicleManufacturer;

use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleManufacturerHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
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
    #[Route(path: '/vehicle-manufacturers', name: 'vehicle-manufacturers')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, VehicleManufacturerHelper $vehicleManufacturerHelper): Response
    {
        if (!$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $vehicleManufacturerHelper->getFilter();

        return $this->render(
            'VehicleManufacturer/index.html.twig',
            [
                'filter'               => $filter,
                'totalNames'           => $vehicleManufacturerHelper->getTotalNames(),
                'vehicleManufacturers' => $vehicleManufacturerHelper->getVehicleManufacturers($filter)
            ]
        );
    }
}

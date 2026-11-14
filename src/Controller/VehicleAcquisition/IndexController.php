<?php

namespace App\Controller\VehicleAcquisition;

use App\Entity\VehicleAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleAcquisitionHelper;
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
    #[Route(path: '/vehicle-acquisitions', name: 'vehicle-acquisitions')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, VehicleAcquisitionHelper $vehicleAcquisitionHelper): Response
    {
        if (!$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $vehicleAcquisitionHelper->getFilter();

        return $this->render(
            'VehicleAcquisition/index.html.twig',
            [
                'filter'              => $filter,
                'totalNames'          => $vehicleAcquisitionHelper->getTotalNames(),
                'vehicleAcquisitions' => $vehicleAcquisitionHelper->getVehicleAcquisitions($filter)
            ]
        );
    }
}

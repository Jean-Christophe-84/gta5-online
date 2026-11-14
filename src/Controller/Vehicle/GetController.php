<?php

namespace App\Controller\Vehicle;

use App\Entity\DeliveryMethodLocale;
use App\Entity\Locale;
use App\Entity\ModificationLocationLocale;
use App\Entity\StorageLocationLocale;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAcquisitionLocale;
use App\Entity\VehicleClassLocale;
use App\Entity\VehicleDrivetrainLocale;
use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
use App\Service\DeletionResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/vehicle/get', name: 'get_vehicle')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getVehicle(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, EntityManagerInterface $entityManager, Request $request,
        VehicleHelper       $vehicleHelper
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('vehicleId')) {
            if (!$this->isGranted($delete ? Vehicle::PERMISSION_VEHICLE_DELETE : Vehicle::PERMISSION_VEHICLE_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicle = $vehicleHelper->getVehicle($request->query->get('vehicleId'));
            $copy    = false;
        } elseif ($request->query->get('vehicleCopyId')) {
            if ($delete || !$this->isGranted(Vehicle::PERMISSION_VEHICLE_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicle = $vehicleHelper->getVehicle($request->query->get('vehicleCopyId'));
            $copy    = true;
        } else {
            if ($delete || !$this->isGranted(Vehicle::PERMISSION_VEHICLE_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $vehicle = null;
            $copy    = false;
        }

        $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

        $template = $this->renderView(
            'Vehicle/_partial/body.html.twig',
            [
                'copy'                        => $copy,
                'deliveryMethodLocales'       => $entityManager->getRepository(DeliveryMethodLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'modificationLocationLocales' => $entityManager->getRepository(ModificationLocationLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'properties'                  => $vehicleHelper->getProperties(),
                'wantedProperties'            => $vehicleHelper->getWantedProperties(),
                'storageLocationLocales'      => $entityManager->getRepository(StorageLocationLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'titleUpdates'                => $entityManager->getRepository(TitleUpdate::class)->findBy([], ['name' => 'ASC']),
                'vehicle'                     => $vehicle,
                'vehicleAcquisitionLocales'   => $entityManager->getRepository(VehicleAcquisitionLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'vehicleClassLocales'         => $entityManager->getRepository(VehicleClassLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'vehicleDrivetrainLocales'    => $entityManager->getRepository(VehicleDrivetrainLocale::class)->findBy(
                    ['locale' => $locale], ['name' => 'ASC']
                ),
                'vehicleManufacturers'        => $entityManager->getRepository(VehicleManufacturer::class)->findBy([], ['name' => 'ASC']),
                'vehicleOnes'                 => $entityManager->getRepository(Vehicle::class)->findBy([], ['name' => 'ASC'])
            ]
        );

        if ($delete) {
            return $deletionResponder->json($template, $vehicleHelper->getDeletion((int)$request->query->get('vehicleId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}

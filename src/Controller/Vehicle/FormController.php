<?php

namespace App\Controller\Vehicle;

use App\Entity\DeliveryMethod;
use App\Entity\ModificationLocation;
use App\Entity\Property;
use App\Entity\StorageLocation;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAcquisition;
use App\Entity\VehicleClass;
use App\Entity\VehicleDeliveryMethod;
use App\Entity\VehicleDrivetrain;
use App\Entity\VehicleLocale;
use App\Entity\VehicleManufacturer;
use App\Entity\VehicleModificationLocation;
use App\Entity\VehicleStorageLocation;
use App\Entity\VehicleUser;
use App\Entity\VehicleVehicle;
use App\Entity\VehicleVehicleAcquisition;
use App\Entity\VehicleVehicleClass;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/vehicle/form', name: 'form_vehicle', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        VehicleHelper       $vehicleHelper
    ): Response {
        $type = $request->request->get('vehicleId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, ['name' => 'please_enter_the_name', 'titleUpdateId' => 'please_enter_the_title_update']
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($request->request->get('vehicleManufacturerId')) {
                $vehicleManufacturer = $entityManager->getRepository(VehicleManufacturer::class)->find($request->request->get('vehicleManufacturerId'));
            } else {
                $vehicleManufacturer = null;
            }

            if ($request->request->get('propertyId')) {
                $property = $entityManager->getRepository(Property::class)->find($request->request->get('propertyId'));
            } else {
                $property = null;
            }

            if ($request->request->get('wantedPropertyId')) {
                $wantedProperty = $entityManager->getRepository(Property::class)->find($request->request->get('wantedPropertyId'));
            } else {
                $wantedProperty = null;
            }

            if ($request->request->get('vehicleDrivetrainId')) {
                $vehicleDrivetrain = $entityManager->getRepository(VehicleDrivetrain::class)->find($request->request->get('vehicleDrivetrainId'));
            } else {
                $vehicleDrivetrain = null;
            }

            if ($request->request->get('titleUpdateId')) {
                $titleUpdate = $entityManager->getRepository(TitleUpdate::class)->find($request->request->get('titleUpdateId'));
            } else {
                $titleUpdate = null;
            }

            if ($type === 'update') {
                if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicle = $entityManager->getRepository(Vehicle::class)->find($request->request->get('vehicleId'));
            } else {
                if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicle = new Vehicle();
            }

            /** @var User $user */
            $user          = $this->getUser();
            $vehicleLocale = $entityManager->getRepository(VehicleLocale::class)->findOneBy(['vehicle' => $vehicle, 'locale' => $user->getLocale()]);

            if (trim($request->request->get('comment')) || trim($request->request->get('unlockVehicle'))) {
                if (!$vehicleLocale instanceof VehicleLocale) {
                    $vehicleLocale = new VehicleLocale();
                    $vehicleLocale->setVehicle($vehicle);
                    $vehicleLocale->setLocale($user->getLocale());
                }

                $vehicleLocale->setComment(trim($request->request->get('comment')));
                $vehicleLocale->setUnlockVehicle(trim($request->request->get('unlockVehicle')));

                $entityManager->persist($vehicleLocale);
            } else {
                if ($vehicleLocale instanceof VehicleLocale) {
                    $entityManager->remove($vehicleLocale);
                }
            }

            $vehicleUser = $entityManager->getRepository(VehicleUser::class)->findOneBy(['user' => $user, 'vehicle' => $vehicle]);

            if (!$vehicleUser instanceof VehicleUser) {
                $vehicleUser = new VehicleUser();
                $vehicleUser->setUser($user);
                $vehicleUser->setVehicle($vehicle);
            }

            $vehicleUser->setOwned($request->request->get('owned'));
            $vehicleUser->setProperty($request->request->get('owned') !== "0" ? $property : null);
            $vehicleUser->setWantedProperty($wantedProperty);

            $entityManager->persist($vehicleUser);

            $vehicle->setName(trim($request->request->get('name')));
            $vehicle->setVehicleManufacturer($vehicleManufacturer);
            $vehicle->setEvolution($request->request->get('evolution'));
            $vehicle->setPrice($request->request->get('price') ? : null);
            $vehicle->setDiscountedPrice($request->request->get('discountedPrice') ? : null);
            $vehicle->setDiscountedPercentage($request->request->get('discountedPercentage') ? : null);

            if ($request->request->get('topSpeedKilometers')) {
                $topSpeedMiles      = number_format(($request->request->get('topSpeedKilometers') / 1.609344), 2);
                $topSpeedKilometers = $request->request->get('topSpeedKilometers');
            } else {
                $topSpeedMiles      = null;
                $topSpeedKilometers = null;
            }
            $vehicle->setTopSpeedMiles($topSpeedMiles);
            $vehicle->setTopSpeedKilometers($topSpeedKilometers);

            if ($request->request->get('topSpeedKilometersMaximum')) {
                $topSpeedMilesMaximum      = number_format(($request->request->get('topSpeedKilometersMaximum') / 1.609344), 2);
                $topSpeedKilometersMaximum = $request->request->get('topSpeedKilometersMaximum');
            } else {
                $topSpeedMilesMaximum      = null;
                $topSpeedKilometersMaximum = null;
            }
            $vehicle->setTopSpeedMilesMaximum($topSpeedMilesMaximum);
            $vehicle->setTopSpeedKilometersMaximum($topSpeedKilometersMaximum);
            $vehicle->setWeight($request->request->get('weight') ? : null);
            $vehicle->setSeats($request->request->get('seats') ? : null);
            $vehicle->setGears($request->request->get('gears') ? : null);
            $vehicle->setOptimized($request->request->get('optimized'));
            $vehicle->setVehicleDrivetrain($vehicleDrivetrain);
            $vehicle->setTitleUpdate($titleUpdate);
            $vehicle->setSpeed($request->request->get('speed') ? : null);
            $vehicle->setAcceleration($request->request->get('acceleration') ? : null);
            $vehicle->setBraking($request->request->get('braking') ? : null);
            $vehicle->setHandling($request->request->get('handling') ? : null);

            if ($this->isGranted('ROLE_ADMIN')) {
                $vehicle->setUrl(trim($request->request->get('url')) ? : null);
            }

            $vehicle->setRealLifeVehicle(trim($request->request->get('realLifeVehicle')) ? : null);

            $entityManager->persist($vehicle);
            $entityManager->flush();

            $vehicleVehicleOnesDb           =
                $configurationHelper->indexByEntityId($entityManager->getRepository(VehicleVehicle::class)->findBy(['vehicleTwo' => $vehicle]), 'VehicleOne');
            $vehicleVehicleClassesDb        = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(VehicleVehicleClass::class)->findBy(['vehicle' => $vehicle]), 'VehicleClass'
            );
            $vehicleVehicleAcquisitionsDb   =
                $configurationHelper->indexByEntityId(
                    $entityManager->getRepository(VehicleVehicleAcquisition::class)->findBy(['vehicle' => $vehicle]), 'VehicleAcquisition'
                );
            $vehicleStorageLocationsDb      = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(VehicleStorageLocation::class)->findBy(['vehicle' => $vehicle]), 'StorageLocation'
            );
            $vehicleDeliveryMethodsDb       = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(VehicleDeliveryMethod::class)->findBy(['vehicle' => $vehicle]), 'DeliveryMethod'
            );
            $vehicleModificationLocationsDb = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(VehicleModificationLocation::class)->findBy(['vehicle' => $vehicle]), 'ModificationLocation'
            );

            // Vehicle twos
            if (!empty($request->request->all('vehicleOneIds'))) {
                $positionVehicleVehicle = 1;

                foreach ($request->request->all('vehicleOneIds') as $vehicleOneId) {
                    if (($vehicleOne = $entityManager->getRepository(Vehicle::class)->find($vehicleOneId)) instanceof Vehicle) {
                        if (isset($vehicleVehicleOnesDb[$vehicleOneId])) {
                            $vehicleVehicle = $vehicleVehicleOnesDb[$vehicleOneId];
                            unset($vehicleVehicleOnesDb[$vehicleOneId]);
                        } else {
                            $vehicleVehicle = new VehicleVehicle();
                            $vehicleVehicle->setVehicleOne($vehicleOne);
                            $vehicleVehicle->setVehicleTwo($vehicle);
                        }

                        $vehicleVehicle->setPosition($positionVehicleVehicle);

                        $entityManager->persist($vehicleVehicle);

                        $positionVehicleVehicle++;
                    }
                }
            }

            foreach ($vehicleVehicleOnesDb as $vehicleVehicleOneToRemove) {
                $entityManager->remove($vehicleVehicleOneToRemove);
            }

            // Vehicle classes
            if (!empty($request->request->all('vehicleClassIds'))) {
                $positionVehicleVehicleClass = 1;

                foreach ($request->request->all('vehicleClassIds') as $vehicleClassId) {
                    if (($vehicleClass = $entityManager->getRepository(VehicleClass::class)->find($vehicleClassId)) instanceof VehicleClass) {
                        if (isset($vehicleVehicleClassesDb[$vehicleClassId])) {
                            $vehicleVehicleClass = $vehicleVehicleClassesDb[$vehicleClassId];
                            unset($vehicleVehicleClassesDb[$vehicleClassId]);
                        } else {
                            $vehicleVehicleClass = new VehicleVehicleClass();
                            $vehicleVehicleClass->setVehicle($vehicle);
                            $vehicleVehicleClass->setVehicleClass($vehicleClass);
                        }

                        $vehicleVehicleClass->setPosition($positionVehicleVehicleClass);

                        $entityManager->persist($vehicleVehicleClass);

                        $positionVehicleVehicleClass++;
                    }
                }
            }

            foreach ($vehicleVehicleClassesDb as $vehicleVehicleClassToRemove) {
                $entityManager->remove($vehicleVehicleClassToRemove);
            }

            // Vehicle Acquisitions
            if (!empty($request->request->all('vehicleAcquisitionIds'))) {
                $positionVehicleVehicleAcquisition = 1;

                foreach ($request->request->all('vehicleAcquisitionIds') as $vehicleAcquisitionId) {
                    $vehicleAcquisition = $entityManager->getRepository(VehicleAcquisition::class)->find($vehicleAcquisitionId);

                    if ($vehicleAcquisition instanceof VehicleAcquisition) {
                        if (isset($vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId])) {
                            $vehicleVehicleAcquisition = $vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId];
                            unset($vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId]);
                        } else {
                            $vehicleVehicleAcquisition = new VehicleVehicleAcquisition();
                            $vehicleVehicleAcquisition->setVehicle($vehicle);
                            $vehicleVehicleAcquisition->setVehicleAcquisition($vehicleAcquisition);
                        }

                        $vehicleVehicleAcquisition->setPosition($positionVehicleVehicleAcquisition);

                        $entityManager->persist($vehicleVehicleAcquisition);

                        $positionVehicleVehicleAcquisition++;
                    }
                }
            }

            foreach ($vehicleVehicleAcquisitionsDb as $vehicleVehicleAcquisitionToRemove) {
                $entityManager->remove($vehicleVehicleAcquisitionToRemove);
            }

            // Storage locations
            if (!empty($request->request->all('storageLocationIds'))) {
                $positionVehicleStorageLocation = 1;

                foreach ($request->request->all('storageLocationIds') as $storageLocationId) {
                    if (($storageLocation = $entityManager->getRepository(StorageLocation::class)->find($storageLocationId)) instanceof StorageLocation) {
                        if (isset($vehicleStorageLocationsDb[$storageLocationId])) {
                            $vehicleStorageLocation = $vehicleStorageLocationsDb[$storageLocationId];
                            unset($vehicleStorageLocationsDb[$storageLocationId]);
                        } else {
                            $vehicleStorageLocation = new VehicleStorageLocation();
                            $vehicleStorageLocation->setVehicle($vehicle);
                            $vehicleStorageLocation->setStorageLocation($storageLocation);
                        }

                        $vehicleStorageLocation->setPosition($positionVehicleStorageLocation);

                        $entityManager->persist($vehicleStorageLocation);

                        $positionVehicleStorageLocation++;
                    }
                }
            }

            foreach ($vehicleStorageLocationsDb as $vehicleStorageLocationToRemove) {
                $entityManager->remove($vehicleStorageLocationToRemove);
            }

            // Delivery methods
            if (!empty($request->request->all('deliveryMethodIds'))) {
                $positionVehicleDeliveryMethod = 1;

                foreach ($request->request->all('deliveryMethodIds') as $deliveryMethodId) {
                    if (($deliveryMethod = $entityManager->getRepository(DeliveryMethod::class)->find($deliveryMethodId)) instanceof DeliveryMethod) {
                        if (isset($vehicleDeliveryMethodsDb[$deliveryMethodId])) {
                            $vehicleDeliveryMethod = $vehicleDeliveryMethodsDb[$deliveryMethodId];
                            unset($vehicleDeliveryMethodsDb[$deliveryMethodId]);
                        } else {
                            $vehicleDeliveryMethod = new VehicleDeliveryMethod();
                            $vehicleDeliveryMethod->setVehicle($vehicle);
                            $vehicleDeliveryMethod->setDeliveryMethod($deliveryMethod);
                        }

                        $vehicleDeliveryMethod->setPosition($positionVehicleDeliveryMethod);

                        $entityManager->persist($vehicleDeliveryMethod);

                        $positionVehicleDeliveryMethod++;
                    }
                }
            }

            foreach ($vehicleDeliveryMethodsDb as $vehicleDeliveryMethodToRemove) {
                $entityManager->remove($vehicleDeliveryMethodToRemove);
            }

            // Modification locations
            if (!empty($request->request->all('modificationLocationIds'))) {
                $positionVehicleModificationLocation = 1;

                foreach ($request->request->all('modificationLocationIds') as $modificationLocationId) {
                    $modificationLocation = $entityManager->getRepository(ModificationLocation::class)->find($modificationLocationId);

                    if ($modificationLocation instanceof ModificationLocation) {
                        if (isset($vehicleModificationLocationsDb[$modificationLocationId])) {
                            $vehicleModificationLocation = $vehicleModificationLocationsDb[$modificationLocationId];
                            unset($vehicleModificationLocationsDb[$modificationLocationId]);
                        } else {
                            $vehicleModificationLocation = new VehicleModificationLocation();
                            $vehicleModificationLocation->setVehicle($vehicle);
                            $vehicleModificationLocation->setModificationLocation($modificationLocation);
                        }

                        $vehicleModificationLocation->setPosition($positionVehicleModificationLocation);

                        $entityManager->persist($vehicleModificationLocation);

                        $positionVehicleModificationLocation++;
                    }
                }
            }

            foreach ($vehicleModificationLocationsDb as $vehicleModificationLocationToRemove) {
                $entityManager->remove($vehicleModificationLocationToRemove);
            }

            $entityManager->flush();

            if ($request->request->get('owned') && $request->request->get('owned') !== "0") {
                $vehicleHelper->releaseLinkedEvolutions($vehicle->id);
            }

            $parameters = $vehicleHelper->getParameters($vehicleHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Vehicle/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Vehicle/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_vehicle_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'vehicles', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Vehicle/_partial/template.html.twig', $parameters),
                    'vehicles'  => $parameters['vehicles']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_vehicle_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

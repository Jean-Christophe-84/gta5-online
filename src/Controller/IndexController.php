<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Helper\BountyTargetHelper;
use App\Helper\BusinessGoodHelper;
use App\Helper\BusinessHelper;
use App\Helper\CustomizationHelper;
use App\Helper\DeliveryMethodHelper;
use App\Helper\GroupGtaHelper;
use App\Helper\LoginErrorHelper;
use App\Helper\LoginSuccessHelper;
use App\Helper\ModificationLocationHelper;
use App\Helper\PropertyAcquisitionHelper;
use App\Helper\PropertyHelper;
use App\Helper\PropertyTypeHelper;
use App\Helper\StorageLocationHelper;
use App\Helper\TitleUpdateHelper;
use App\Helper\UserHelper;
use App\Helper\VehicleAcquisitionHelper;
use App\Helper\VehicleHelper;
use App\Helper\VehicleManufacturerHelper;
use App\Helper\WeaponAcquisitionHelper;
use App\Helper\WeaponHelper;
use App\Helper\WeaponManufacturerHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/', name: 'homepage')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(
        BountyTargetHelper       $bountyTargetHelper, BusinessHelper $businessHelper, BusinessGoodHelper $businessGoodHelper, CustomizationHelper $customizationHelper,
        DeliveryMethodHelper     $deliveryMethodHelper, EntityManagerInterface $entityManager, GroupGtaHelper $groupGtaHelper, LoginErrorHelper $loginErrorHelper,
        LoginSuccessHelper       $loginSuccessHelper, ModificationLocationHelper $modificationLocationHelper,
        PropertyHelper           $propertyHelper, PropertyAcquisitionHelper $propertyAcquisitionHelper, PropertyTypeHelper $propertyTypeHelper,
        StorageLocationHelper    $storageLocationHelper, TitleUpdateHelper $titleUpdateHelper, UserHelper $userHelper,
        VehicleAcquisitionHelper $vehicleAcquisitionHelper, VehicleHelper $vehicleHelper, VehicleManufacturerHelper $vehicleManufacturerHelper,
        WeaponHelper             $weaponHelper, WeaponAcquisitionHelper $weaponAcquisitionHelper, WeaponManufacturerHelper $weaponManufacturerHelper
    ): Response {
        return $this->render(
            'index.html.twig',
            [
                'configuration'              => $entityManager->getRepository(Configuration::class)->find(1),
                'promotionProperties'        => $this->isGranted('PROPERTY_VIEW') ? $propertyHelper->getSummary($propertyHelper->getFilter(true)) : null,
                'promotionVehicles'          => $this->isGranted('VEHICLE_VIEW') ? $vehicleHelper->getSummary($vehicleHelper->getFilter(true)) : null,
                'promotionWeapons'           => $this->isGranted('WEAPON_VIEW') ? $weaponHelper->getSummary($weaponHelper->getFilter(true)) : null,
                'totalBountyTargets'         => $this->isGranted('BOUNTY_TARGET_VIEW') ? $bountyTargetHelper->getTotalNames(true) : null,
                'totalBusinesses'            => $this->isGranted('BUSINESS_VIEW') ? $businessHelper->getTotalNames(true) : null,
                'totalBusinessGoods'         => $this->isGranted('BUSINESS_GOOD_VIEW') ? $businessGoodHelper->getTotalNames(true) : null,
                'totalCustomizations'        => $this->isGranted('CUSTOMIZATION_VIEW') ? $customizationHelper->getTotalNames(true) : null,
                'totalDeliveryMethods'       => $this->isGranted('DELIVERY_METHOD_VIEW') ? $deliveryMethodHelper->getTotalNames(true) : null,
                'totalGroupGtas'             => $this->isGranted('ROLE_ADMIN') ? $groupGtaHelper->getTotalNames(true) : null,
                'totalLoginErrors'           => $this->isGranted('ROLE_ADMIN') ? $loginErrorHelper->getTotalNames(true) : null,
                'totalLoginSuccesses'        => $this->isGranted('ROLE_ADMIN') ? $loginSuccessHelper->getTotalNames(true) : null,
                'totalModificationLocations' => $this->isGranted('MODIFICATION_LOCATION_VIEW') ? $modificationLocationHelper->getTotalNames(true) : null,
                'totalProperties'            => $this->isGranted('PROPERTY_VIEW') ? $propertyHelper->getTotalNames(true) : null,
                'totalPropertyAcquisitions'  => $this->isGranted('PROPERTY_ACQUISITION_VIEW') ? $propertyAcquisitionHelper->getTotalNames(true) : null,
                'totalPropertyTypes'         => $this->isGranted('PROPERTY_TYPE_VIEW') ? $propertyTypeHelper->getTotalNames(true) : null,
                'totalStorageLocations'      => $this->isGranted('STORAGE_LOCATION_VIEW') ? $storageLocationHelper->getTotalNames(true) : null,
                'totalTitleUpdates'          => $this->isGranted('TITLE_UPDATE_VIEW') ? $titleUpdateHelper->getTotalNames(true) : null,
                'totalUsers'                 => $this->isGranted('ROLE_ADMIN') ? $userHelper->getTotalNames(true) : null,
                'totalVehicleAcquisitions'   => $this->isGranted('VEHICLE_ACQUISITION_VIEW') ? $vehicleAcquisitionHelper->getTotalNames(true) : null,
                'totalVehicleManufacturers'  => $this->isGranted('VEHICLE_MANUFACTURER_VIEW') ? $vehicleManufacturerHelper->getTotalNames(true) : null,
                'totalVehicles'              => $this->isGranted('VEHICLE_VIEW') ? $vehicleHelper->getTotalNames(true) : null,
                'totalWeaponAcquisitions'    => $this->isGranted('WEAPON_ACQUISITION_VIEW') ? $weaponAcquisitionHelper->getTotalNames(true) : null,
                'totalWeaponManufacturers'   => $this->isGranted('WEAPON_MANUFACTURER_VIEW') ? $weaponManufacturerHelper->getTotalNames(true) : null,
                'totalWeapons'               => $this->isGranted('WEAPON_VIEW') ? $weaponHelper->getTotalNames(true) : null
            ]
        );
    }
}

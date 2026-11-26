<?php

namespace App\Command;

use App\Entity\Configuration;
use App\Entity\DeliveryMethodLocale;
use App\Entity\Locale;
use App\Entity\ModificationLocationLocale;
use App\Entity\StorageLocationLocale;
use App\Entity\TitleUpdate;
use App\Entity\Vehicle;
use App\Entity\VehicleAcquisitionLocale;
use App\Entity\VehicleClassLocale;
use App\Entity\VehicleDeliveryMethod;
use App\Entity\VehicleDrivetrain;
use App\Entity\VehicleLocale;
use App\Entity\VehicleManufacturer;
use App\Entity\VehicleModificationLocation;
use App\Entity\VehicleStorageLocation;
use App\Entity\VehicleVehicle;
use App\Entity\VehicleVehicleAcquisition;
use App\Entity\VehicleVehicleClass;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleHelper;
use DeepL\DeepLException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMXPath;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'GetVehiclesCommand',
    description: 'Get vehicles from GTABase',
    aliases    : ['vehicles:get']
)]
class GetVehiclesCommand extends Command
{
    public function __construct(
        private readonly ConfigurationHelper   $configurationHelper, private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag, private readonly VehicleHelper $vehicleHelper
    ) {
        parent::__construct(AsCommand::class);
    }

    /**
     * @throws DoctrineDBALException|Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->entityManager->getRepository(Configuration::class)->find(1);
        $urls          = $this->vehicleHelper->getUrls();

        $vehicleManufacturersDb      = $this->indexByName($this->entityManager->getRepository(VehicleManufacturer::class)->findAll());
        $vehicleDrivetrainLocalesDb  = $this->indexByShortName($this->entityManager->getRepository(VehicleDrivetrain::class)->findAll());
        $titleUpdatesDb              = $this->indexByName($this->entityManager->getRepository(TitleUpdate::class)->findAll());
        $vehicleAcquisitionLocalesDb = $this->indexByName($this->entityManager->getRepository(VehicleAcquisitionLocale::class)->findAll());
        $deliveryMethodLocalesDb     = $this->indexByName($this->entityManager->getRepository(DeliveryMethodLocale::class)->findAll());
        $modificationLocationsDb     = $this->indexByName($this->entityManager->getRepository(ModificationLocationLocale::class)->findAll());
        $storageLocationLocalesDb    = $this->indexByName($this->entityManager->getRepository(StorageLocationLocale::class)->findAll());
        $vehiclesDb                  = $this->indexByName($this->entityManager->getRepository(Vehicle::class)->findAll());
        $vehicleClassLocalesDb       = $this->indexByName($this->entityManager->getRepository(VehicleClassLocale::class)->findAll());
        $localesDb                   = $this->entityManager->getRepository(Locale::class)->findAll();
        $localeEnDb                  = $this->entityManager->getRepository(Locale::class)->findOneBy(['code' => 'en']);
        $numberDatas                 = count($urls);
        $i                           = 1;

        $progressBar = new ProgressBar($output, $numberDatas);
        $progressBar->setFormat('debug');

        foreach ($urls as $url) {
            $this->processVehicleItem(
                $url, $vehicleManufacturersDb, $vehicleDrivetrainLocalesDb, $titleUpdatesDb, $vehicleAcquisitionLocalesDb, $deliveryMethodLocalesDb,
                $modificationLocationsDb, $storageLocationLocalesDb, $vehiclesDb, $vehicleClassLocalesDb, $localesDb, $localeEnDb
            );

            if ($numberDatas > 0 && round(($i / $numberDatas) * 100) > 0) {
                $configuration->setPercentageVehicles(round(($i / $numberDatas) * 100));
            }

            $this->entityManager->persist($configuration);
            $this->entityManager->flush();

            $i++;

            $progressBar->advance();
        }

        $progressBar->finish();

        $configuration->setPercentageVehicles(0);

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function processVehicleItem(
        string $url, array$vehicleManufacturersDb, array $vehicleDrivetrainLocalesDb, array $titleUpdatesDb, array $vehicleAcquisitionLocalesDb,
        array $deliveryMethodLocalesDb, array $modificationLocationsDb, array $storageLocationLocalesDb, array $vehiclesDb, array $vehicleClassLocalesDb,
        array $localesDb, Locale $localeEnDb
    ): void {
        $page = file_get_contents($url);

        $document = new DOMDocument();
        @$document->loadHTML($page);

        $xpath = new DOMXPath($document);

        if (!$this->isGtaOnline($xpath)) {
            if ($vehicle = $this->getVehicle($xpath, $vehiclesDb)) {
                throw new Exception("The following vehicle does not exist in GTA 5 Online: " . $vehicle->getName() . " (" . $vehicle->id . ")");
            }
        } else {
            if (!$vehicle = $this->getVehicle($xpath, $vehiclesDb)) {
                $vehicle = new Vehicle();
                $vehicle->setName($this->getVehicleName($xpath));
            }

            $vehicle->setVehicleManufacturer($this->getVehicleManufacturer($xpath, $vehicleManufacturersDb));
            $vehicle->setVehicleDrivetrain($this->getVehicleDrivetrain($xpath, $vehicleDrivetrainLocalesDb));
            $vehicle->setTitleUpdate($this->getTitleUpdate($xpath, $titleUpdatesDb));
            $vehicle->setPath($this->saveImage($xpath, $vehicle->id));
            $vehicle->setUrl($url);
            $vehicle->setRealLifeVehicle($this->getRealLifeVehicle($xpath));
            $vehicle->setPrice($this->getPrice($xpath));
            $vehicle->setDiscountedPrice($this->getTradePrice($xpath));
            $vehicle->setEvolution($this->getEvolution($xpath, $localeEnDb));
            $vehicle->setTopSpeedMiles($this->getTopSpeed($xpath));
            $vehicle->setTopSpeedKilometers($this->getTopSpeed($xpath, true));
            $vehicle->setSeats($this->getSeats($xpath));
            $vehicle->setWeight($this->getWeight($xpath));
            $vehicle->setGears($this->getGears($xpath));
            $vehicle->setTopSpeedMilesMaximum($this->getTopSpeedBroughy($xpath));
            $vehicle->setTopSpeedKilometersMaximum($this->getTopSpeedBroughy($xpath, true));
            $vehicle->setSpeed($this->getSpeed($xpath));
            $vehicle->setAcceleration($this->getAcceleration($xpath));
            $vehicle->setBraking($this->getBraking($xpath));
            $vehicle->setHandling($this->getHandling($xpath));

            $this->entityManager->persist($vehicle);

            foreach ($localesDb as $localeDb) {
                $comment       = $this->getComment($xpath, $localeDb);
                $unlockVehicle = $this->getHowToUnlock($xpath, $localeDb);

                if ($comment || $unlockVehicle) {
                    $vehicleLocale = new VehicleLocale();
                    $vehicleLocale->setVehicle($vehicle);
                    $vehicleLocale->setLocale($localeDb);
                    $vehicleLocale->setComment($comment);
                    $vehicleLocale->setUnlockVehicle($unlockVehicle);

                    $this->entityManager->persist($vehicleLocale);
                }
            }

            $vehicleVehicleOnesDb           = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleVehicle::class)->findBy(['vehicleTwo' => $vehicle]), 'VehicleOne');
            $vehicleVehicleTwosDb           = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleVehicle::class)->findBy(['vehicleOne' => $vehicle]), 'VehicleTwo');
            $vehicleVehicleAcquisitionsDb   = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleVehicleAcquisition::class)->findBy(['vehicle' => $vehicle]), 'VehicleAcquisition');
            $vehicleDeliveryMethodsDb       = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleDeliveryMethod::class)->findBy(['vehicle' => $vehicle]), 'DeliveryMethod');
            $vehicleModificationLocationsDb = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleModificationLocation::class)->findBy(['vehicle' => $vehicle]), 'ModificationLocation');
            $vehicleStorageLocationsDb      = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleStorageLocation::class)->findBy(['vehicle' => $vehicle]), 'StorageLocation');
            $vehicleVehicleClassesDb        = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(VehicleVehicleClass::class)->findBy(['vehicle' => $vehicle]), 'VehicleClass');

            // Vehicle ones
            if (!empty($vehicleOnes = $this->getVehicleOnes($xpath, $vehiclesDb))) {
                $positionVehicleVehicleOne = 1;

                foreach ($vehicleOnes as $vehicleOne) {
                    $vehicleOneId = $vehicleOne->id;

                    if (isset($vehicleVehicleOnesDb[$vehicleOneId])) {
                        $vehicleVehicle = $vehicleVehicleOnesDb[$vehicleOneId];
                        unset($vehicleVehicleOnesDb[$vehicleOneId]);
                    } else {
                        $vehicleVehicle = new VehicleVehicle();
                        $vehicleVehicle->setVehicleOne($vehicleOne);
                        $vehicleVehicle->setVehicleTwo($vehicle);
                    }

                    $vehicleVehicle->setPosition($positionVehicleVehicleOne);

                    $this->entityManager->persist($vehicleVehicle);

                    $positionVehicleVehicleOne++;
                }
            }

            foreach ($vehicleVehicleOnesDb as $vehicleVehicleToRemove) {
                $this->entityManager->remove($vehicleVehicleToRemove);
            }

            // Vehicle twos
            if (!empty($vehicleTwos = $this->getVehicleTwos($xpath, $vehiclesDb))) {
                $positionVehicleVehicleTwo = 1;

                foreach ($vehicleTwos as $vehicleTwo) {
                    $vehicleTwoId = $vehicleTwo->id;

                    if (isset($vehicleVehicleTwosDb[$vehicleTwoId])) {
                        $vehicleVehicle = $vehicleVehicleTwosDb[$vehicleTwoId];
                        unset($vehicleVehicleTwosDb[$vehicleTwoId]);
                    } else {
                        $vehicleVehicle = new VehicleVehicle();
                        $vehicleVehicle->setVehicleOne($vehicle);
                        $vehicleVehicle->setVehicleTwo($vehicleTwo);
                    }

                    $vehicleVehicle->setPosition($positionVehicleVehicleTwo);

                    $this->entityManager->persist($vehicleVehicle);

                    $positionVehicleVehicleTwo++;
                }
            }

            foreach ($vehicleVehicleTwosDb as $vehicleVehicleToRemove) {
                $this->entityManager->remove($vehicleVehicleToRemove);
            }

            // Vehicle Acquisitions
            if (!empty($vehicleAcquisitions = $this->getVehicleAcquisitions($xpath, $vehicleAcquisitionLocalesDb))) {
                $positionVehicleVehicleAcquisition = 1;

                foreach ($vehicleAcquisitions as $vehicleAcquisition) {
                    $vehicleAcquisitionId = $vehicleAcquisition->id;

                    if (isset($vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId])) {
                        $vehicleVehicleAcquisition = $vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId];
                        unset($vehicleVehicleAcquisitionsDb[$vehicleAcquisitionId]);
                    } else {
                        $vehicleVehicleAcquisition = new VehicleVehicleAcquisition();
                        $vehicleVehicleAcquisition->setVehicle($vehicle);
                        $vehicleVehicleAcquisition->setVehicleAcquisition($vehicleAcquisition);
                    }

                    $vehicleVehicleAcquisition->setPosition($positionVehicleVehicleAcquisition);

                    $this->entityManager->persist($vehicleVehicleAcquisition);

                    $positionVehicleVehicleAcquisition++;
                }
            }

            foreach ($vehicleVehicleAcquisitionsDb as $vehicleVehicleAcquisitionToRemove) {
                if (!$vehicleVehicleAcquisitionToRemove->getVehicleAcquisition()->isPromotion()) {
                    $this->entityManager->remove($vehicleVehicleAcquisitionToRemove);
                }
            }

            // Delivery methods
            if (!empty($deliveryMethods = $this->getDeliveryMethods($xpath, $deliveryMethodLocalesDb))) {
                $positionVehicleDeliveryMethod = 1;

                foreach ($deliveryMethods as $deliveryMethod) {
                    $deliveryMethodId = $deliveryMethod->id;

                    if (isset($vehicleDeliveryMethodsDb[$deliveryMethodId])) {
                        $vehicleDeliveryMethod = $vehicleDeliveryMethodsDb[$deliveryMethodId];
                        unset($vehicleDeliveryMethodsDb[$deliveryMethodId]);
                    } else {
                        $vehicleDeliveryMethod = new VehicleDeliveryMethod();
                        $vehicleDeliveryMethod->setVehicle($vehicle);
                        $vehicleDeliveryMethod->setDeliveryMethod($deliveryMethod);
                    }

                    $vehicleDeliveryMethod->setPosition($positionVehicleDeliveryMethod);

                    $this->entityManager->persist($vehicleDeliveryMethod);

                    $positionVehicleDeliveryMethod++;
                }
            }

            foreach ($vehicleDeliveryMethodsDb as $vehicleDeliveryMethodToRemove) {
                $this->entityManager->remove($vehicleDeliveryMethodToRemove);
            }

            // Modification Locations
            if (!empty($modificationLocations = $this->getModificationLocations($xpath, $modificationLocationsDb))) {
                $positionVehicleModificationLocation = 1;

                foreach ($modificationLocations as $modificationLocation) {
                    $modificationLocationId = $modificationLocation->id;

                    if (isset($vehicleModificationLocationsDb[$modificationLocationId])) {
                        $vehicleModificationLocation = $vehicleModificationLocationsDb[$modificationLocationId];
                        unset($vehicleModificationLocationsDb[$modificationLocationId]);
                    } else {
                        $vehicleModificationLocation = new VehicleModificationLocation();
                        $vehicleModificationLocation->setVehicle($vehicle);
                        $vehicleModificationLocation->setModificationLocation($modificationLocation);
                    }

                    $vehicleModificationLocation->setPosition($positionVehicleModificationLocation);

                    $this->entityManager->persist($vehicleModificationLocation);

                    $positionVehicleModificationLocation++;
                }
            }

            foreach ($vehicleModificationLocationsDb as $vehicleModificationLocationToRemove) {
                $this->entityManager->remove($vehicleModificationLocationToRemove);
            }

            // Storage Locations
            if (!empty($storageLocations = $this->getStorageLocations($xpath, $storageLocationLocalesDb))) {
                $positionVehicleStorageLocation = 1;

                foreach ($storageLocations as $storageLocation) {
                    $storageLocationId = $storageLocation->id;

                    if (isset($vehicleStorageLocationsDb[$storageLocationId])) {
                        $vehicleStorageLocation = $vehicleStorageLocationsDb[$storageLocationId];
                        unset($vehicleStorageLocationsDb[$storageLocationId]);
                    } else {
                        $vehicleStorageLocation = new VehicleStorageLocation();
                        $vehicleStorageLocation->setVehicle($vehicle);
                        $vehicleStorageLocation->setStorageLocation($storageLocation);
                    }

                    $vehicleStorageLocation->setPosition($positionVehicleStorageLocation);

                    $this->entityManager->persist($vehicleStorageLocation);

                    $positionVehicleStorageLocation++;
                }
            }

            foreach ($vehicleStorageLocationsDb as $vehicleStorageLocationToRemove) {
                $this->entityManager->remove($vehicleStorageLocationToRemove);
            }

            // Vehicle classes
            if (!empty($vehicleClasses = $this->getVehicleClasses($xpath, $vehicleClassLocalesDb))) {
                $positionVehicleVehicleClass = 1;

                foreach ($vehicleClasses as $vehicleClass) {
                    $vehicleClassId = $vehicleClass->id;

                    if (isset($vehicleVehicleClassesDb[$vehicleClassId])) {
                        $vehicleVehicleClass = $vehicleVehicleClassesDb[$vehicleClassId];
                        unset($vehicleVehicleClassesDb[$vehicleClassId]);
                    } else {
                        $vehicleVehicleClass = new VehicleVehicleClass();
                        $vehicleVehicleClass->setVehicle($vehicle);
                        $vehicleVehicleClass->setVehicleClass($vehicleClass);
                    }

                    $vehicleVehicleClass->setPosition($positionVehicleVehicleClass);

                    $this->entityManager->persist($vehicleVehicleClass);

                    $positionVehicleVehicleClass++;
                }
            }

            foreach ($vehicleVehicleClassesDb as $vehicleVehicleClassToRemove) {
                $this->entityManager->remove($vehicleVehicleClassToRemove);
            }
        }
    }

    private function findNamedEntity(string $name, array $entitiesByName): ?object
    {
        return $entitiesByName[$name] ?? null;
    }

    private function getAcceleration(DOMXPath $xpath): ?float
    {
        $acceleration = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " acceleration ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $acceleration !== null ? round((float)$acceleration, 2) : null;
    }

    private function getBraking(DOMXPath $xpath): ?float
    {
        $braking = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " braking ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $braking !== null ? round((float)$braking, 2) : null;
    }

    /**
     * @throws DeepLException
     */
    private function getComment(DOMXPath $xpath, Locale $localeDb): ?string
    {
        if ($commentNode =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " notes ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)
        ) {
            if (str_starts_with(trim($commentNode->textContent), '*')) {
                $comment = substr(trim($commentNode->textContent), 1);
            } else {
                $comment = $commentNode->textContent;
            }
        } else {
            $comment = null;
        }

        if ($comment) {
            if ($localeDb->getCode() != 'en') {
                return $this->configurationHelper->translate($comment, null, $localeDb->getCode());
            }

            return $comment;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getDeliveryMethods(DOMXPath $xpath, array $deliveryMethodLocalesDb): array
    {
        $deliveryMethods = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " request-delivery ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $deliveryMethodName = explode('Delivery Method: ', $node->getAttribute('title'));

                $deliveryMethodLocale = $this->findNamedEntity($deliveryMethodName[1], $deliveryMethodLocalesDb);

                if (!$deliveryMethodLocale) {
                    throw new Exception(
                        'Unable to find the delivery method "' . $deliveryMethodName[1] . '" for vehicle: ' . $this->getVehicleName($xpath)
                    );
                }

                $deliveryMethods[$deliveryMethodLocale->getDeliveryMethod()->id] = $deliveryMethodLocale->getDeliveryMethod();
            }
        }

        return array_values($deliveryMethods);
    }

    /**
     * @throws DeepLException
     */
    private function getEvolution(DOMXPath $xpath, Locale $localeDb): bool
    {
        $howToUnlock = $this->getHowToUnlock($xpath, $localeDb);

        return $howToUnlock !== null && str_contains($howToUnlock, 'variant');
    }

    private function getGears(DOMXPath $xpath): ?int
    {
        return $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " gears ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;
    }

    private function getHandling(DOMXPath $xpath): ?float
    {
        $handling = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " handling ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $handling !== null ? round((float)$handling, 2) : null;
    }

    /**
     * @throws DeepLException
     */
    private function getHowToUnlock(DOMXPath $xpath, Locale $localeDb): ?string
    {
        $howToUnlock = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " how-to-unlock ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        if ($howToUnlock) {
            if ($localeDb->getCode() != 'en') {
                return $this->configurationHelper->translate($howToUnlock, null, $localeDb->getCode());
            }

            return $howToUnlock;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getModificationLocations(DOMXPath $xpath, array $modificationLocationLocalesDb): array
    {
        $modificationLocations = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " modifications ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $modificationLocationName = explode('Modifications: ', $node->getAttribute('title'));

                $modificationLocationLocale = $this->findNamedEntity($modificationLocationName[1], $modificationLocationLocalesDb);

                if (!$modificationLocationLocale) {
                    throw new Exception(
                        'Unable to find the modification location "' . $modificationLocationName[1] . '" for vehicle: ' . $this->getVehicleName($xpath)
                    );
                }

                $modificationLocations[$modificationLocationLocale->getModificationLocation()->id] = $modificationLocationLocale->getModificationLocation();
            }
        }

        return array_values($modificationLocations);
    }

    /**
     * @throws Exception
     */
    private function getNode(DOMXPath $xpath, string $nodeName, ?string $separator = null): ?string
    {
        $nodes = $xpath->query('//' . $nodeName);

        if ($separator !== null) {
            if (isset($nodes[0])) {
                $title = explode($separator, $nodes[0]->getAttribute('title') ?? null);

                return $title[1] ?? null;
            }

            return null;
        }

        return $nodes?->item(0)?->textContent;
    }

    private function getPrice(DOMXPath $xpath): ?int
    {
        $price = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " price ")]' .
            '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $price !== null ? (int)str_replace(['$', ','], ['', ''], $price) : null;
    }

    private function getRealLifeVehicle(DOMXPath $xpath): ?string
    {
        return $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " based-on ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;
    }

    private function getSeats(DOMXPath $xpath): int
    {
        return (int)$xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " seats ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;
    }

    private function getSpeed(DOMXPath $xpath): ?float
    {
        $speed = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " speed ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $speed !== null ? round((float)$speed, 2) : null;
    }

    /**
     * @throws Exception
     */
    private function getStorageLocations(DOMXPath $xpath, array $storageLocationLocalesDb): array
    {
        $storageLocations = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " storage ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $storageLocationName = explode('Storage Location: ', $node->getAttribute('title'));

                $storageLocationLocale = $this->findNamedEntity($storageLocationName[1], $storageLocationLocalesDb);

                if (!$storageLocationLocale) {
                    throw new Exception(
                        'Unable to find the storage location "' . $storageLocationName[1] . '" for vehicle: ' . $this->getVehicleName($xpath)
                    );
                }

                $storageLocations[$storageLocationLocale->getStorageLocation()->id] = $storageLocationLocale->getStorageLocation();
            }
        }

        return array_values($storageLocations);
    }

    private function getThumbnail(DOMXPath $xpath): string
    {
        $source = $xpath->query('//figure[contains(@class, "item-image")]//img[@src]')?->item(0)?->getAttribute('src');

        if (str_contains($source, 'https://www.gtabase.com')) {
            return $source;
        }

        return "https://www.gtabase.com" . $source;
    }

    /**
     * @throws Exception
     */
    private function getTitleUpdate(DOMXPath $xpath, array $titleUpdatesDb): ?TitleUpdate
    {
        if ($titleUpdateName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " update ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (!isset($titleUpdatesDb[$titleUpdateName])) {
                throw new Exception('Unable to find the title update "' . $titleUpdateName . '" for vehicle: ' . $this->getVehicleName($xpath));
            }

            return $titleUpdatesDb[$titleUpdateName];
        }

        return null;
    }

    private function getTopSpeed(DOMXPath $xpath, bool $inKilometers = false): ?float
    {
        $topSpeedMilesNode = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " top-speed ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0);

        if ($topSpeedMilesNode) {
            $topSpeedMiles = explode('mph', $topSpeedMilesNode->textContent);

            return $inKilometers ? round((float)$topSpeedMiles[0] * 1.609344, 2) : round((float)$topSpeedMiles[0], 2);
        }

        return null;
    }

    private function getTopSpeedBroughy(DOMXPath $xpath, bool $inKilometers = false): ?float
    {
        $topSpeedMilesMaximumNode = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " top-speed-broughy ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0);

        if ($topSpeedMilesMaximumNode) {
            $topSpeedMilesMaximum = explode('mph', $topSpeedMilesMaximumNode->textContent);

            return $inKilometers ? round((float)$topSpeedMilesMaximum[0] * 1.609344, 2) : round((float)$topSpeedMilesMaximum[0], 2);
        }

        return null;
    }

    private function getTradePrice(DOMXPath $xpath): ?int
    {
        $tradePrice = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " trade-price ")]' .
            '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]' .
            '/text()'
        )?->item(0)?->textContent;

        return $tradePrice !== null ? (int)str_replace(['$', ','], ['', ''], $tradePrice) : null;
    }

    /**
     * @throws Exception
     */
    private function getVehicle(DOMXPath $xpath, array $vehiclesDb): Vehicle|bool|null
    {
        if ($vehicleName = $this->getVehicleName($xpath)) {
            if (!isset($vehiclesDb[$vehicleName])) {
                return false;
            }

            return $vehiclesDb[$vehicleName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getVehicleAcquisitions(DOMXPath $xpath, array $vehicleAcquisitionLocalesDb): array
    {
        $vehicleAcquisitions = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " purchase ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $vehicleAcquisitionName = explode('Acquisition: ', $node->getAttribute('title'));

                $vehicleAcquisitionLocale = $this->findNamedEntity($vehicleAcquisitionName[1], $vehicleAcquisitionLocalesDb);

                if (!$vehicleAcquisitionLocale) {
                    throw new Exception(
                        'Unable to find the vehicle acquisition "' . $vehicleAcquisitionName[1] . '" for vehicle: ' . $this->getVehicleName($xpath)
                    );
                }

                $vehicleAcquisitions[$vehicleAcquisitionLocale->getVehicleAcquisition()->id] = $vehicleAcquisitionLocale->getVehicleAcquisition();
            }
        }

        return array_values($vehicleAcquisitions);
    }

    /**
     * @throws Exception
     */
    private function getVehicleClasses(DOMXPath $xpath, array $vehicleClassLocalesDb): array
    {
        $vehicleClasses = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " vehicle-class ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $vehicleClassName = explode('Vehicle Class: ', $node->getAttribute('title'));

                $vehicleClassLocale = $this->findNamedEntity($vehicleClassName[1], $vehicleClassLocalesDb);

                if (!$vehicleClassLocale) {
                    throw new Exception(
                        'Unable to find the vehicle class "' . $vehicleClassName[1] . '" for vehicle: ' . $this->getVehicleName($xpath)
                    );
                }

                $vehicleClasses[$vehicleClassLocale->getVehicleClass()->id] = $vehicleClassLocale->getVehicleClass();
            }
        }

        return array_values($vehicleClasses);
    }

    /**
     * @throws Exception
     */
    private function getVehicleDrivetrain(DOMXPath $xpath, array $vehicleDrivetrainLocalesDb): ?VehicleDrivetrain
    {
        if ($vehicleDrivetrainName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " drivetrain ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (isset($vehicleDrivetrainLocalesDb[$vehicleDrivetrainName])) {
                return $vehicleDrivetrainLocalesDb[$vehicleDrivetrainName];
            }

            throw new Exception('Unable to find the drivetrain "' . $vehicleDrivetrainName . '" for vehicle: ' . $this->getVehicleName($xpath));
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getVehicleManufacturer(DOMXPath $xpath, array $vehicleManufacturersDb): ?VehicleManufacturer
    {
        if ($vehicleManufacturerName = $this->getNode($xpath, 'a[contains(@href, "#attr.ct2.value")]', 'Manufacturer: ')) {
            if (!isset($vehicleManufacturersDb[$vehicleManufacturerName])) {
                throw new Exception('Unable to find the manufacturer "' . $vehicleManufacturerName . '" for vehicle: ' . $this->getVehicleName($xpath));
            }

            return $vehicleManufacturersDb[$vehicleManufacturerName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getVehicleName(DOMXPath $xpath): string
    {
        $nameWithManufacturerName = $this->getNode($xpath, 'h1');
        $manufacturerName         = $this->getNode($xpath, 'a[contains(@href, "#attr.ct2.value")]', 'Manufacturer: ');

        if ($manufacturerName) {
            $name = explode($manufacturerName . ' ', $nameWithManufacturerName);

            return isset($name[1]) ? trim($name[1]) : trim($name[0]);
        }

        return trim($nameWithManufacturerName);
    }

    /**
     * @throws Exception
     */
    private function getVehicleOnes(DOMXPath $xpath, array $vehicleOnesDb): array
    {
        $vehicleOnes = [];

        if ($vehicleOneNames =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " converted-from ")]'
                . '//h3[contains(concat(" ", normalize-space(@class), " "), " contentheading ")]/a'
            )
        ) {
            foreach ($vehicleOneNames as $vehicleOneName) {
                if ($vehicleOne = $this->findNamedEntity(trim($vehicleOneName->textContent), $vehicleOnesDb)) {
                    $vehicleOnes[$vehicleOne->id] = $vehicleOne;
                }
            }
        }

        return array_values($vehicleOnes);
    }

    /**
     * @throws Exception
     */
    private function getVehicleTwos(DOMXPath $xpath, array $vehicleTwosDb): array
    {
        $vehicleTwos = [];

        if ($vehicleTwoNames =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " variant-conversion ")]'
                . '//h3[contains(concat(" ", normalize-space(@class), " "), " contentheading ")]/a'
            )
        ) {
            foreach ($vehicleTwoNames as $vehicleTwoName) {
                if ($vehicleTwo = $this->findNamedEntity(trim($vehicleTwoName->textContent), $vehicleTwosDb)) {
                    $vehicleTwos[$vehicleTwo->id] = $vehicleTwo;
                }
            }
        }

        return array_values($vehicleTwos);
    }

    private function getWeight(DOMXPath $xpath): ?int
    {
        $node = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " weight ")]' .
            '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]' .
            '/text()'
        )?->item(0);

        return $node !== null ? (int)str_replace(',', '', trim($node->textContent)) : null;
    }

    private function indexByShortName(array $entities): array
    {
        $indexedEntities = [];

        foreach ($entities as $entity) {
            $indexedEntities[$entity->getShortName()] = $entity;
        }

        return $indexedEntities;
    }

    private function indexByName(array $entities): array
    {
        $indexedEntities = [];

        foreach ($entities as $entity) {
            $indexedEntities[$entity->getName()] = $entity;
        }

        return $indexedEntities;
    }

    /**
     * @throws Exception
     */
    private function isGtaOnline(DOMXPath $xpath): bool
    {
        return $this->getNode($xpath, 'a[contains(@href, "#attr.ct5.value=gta-online")]') ?? false;
    }

    /**
     * @throws Exception
     */
    private function saveImage(DOMXPath $xpath, ?int $vehicleId): string|null
    {
        $url       = $this->getThumbnail($xpath);
        $localPath =
            $this->parameterBag->get('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR .
            'vehicle';

        if (!$vehicleId) {
            throw new Exception('Unable to find the image "' . $url . '" for vehicle: ' . $this->getVehicleName($xpath));
        }
        $filename = $vehicleId . '.jpg';
        $fullPath = $localPath . '/' . $filename;

        if (!file_exists($fullPath)) {
            $imageContent = file_get_contents($url);

            if ($imageContent !== false) {
                if (file_put_contents($fullPath, $imageContent) !== false) {
                    return $filename;
                }

                return null;
            }

            return null;
        }

        return $filename;
    }
}

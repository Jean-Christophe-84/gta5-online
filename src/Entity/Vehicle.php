<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle')]
class Vehicle
{
    const string PERMISSION_VEHICLE_VIEW          = 'VEHICLE_VIEW';
    const string PERMISSION_VEHICLE_ADD           = 'VEHICLE_ADD';
    const string PERMISSION_VEHICLE_EDIT          = 'VEHICLE_EDIT';
    const string PERMISSION_VEHICLE_CONFIGURATION = 'VEHICLE_CONFIGURATION';
    const string PERMISSION_VEHICLE_OWN           = 'VEHICLE_OWN';
    const string PERMISSION_VEHICLE_DELETE        = 'VEHICLE_DELETE';

    const int FILTER_VEHICLE_TWO_ALL             = 0;
    const int FILTER_VEHICLE_TWO_YES             = 1;
    const int FILTER_VEHICLE_TWO_YES_WITH_OTHERS = 2;
    const int FILTER_VEHICLE_TWO_NO_WITH_OTHERS  = 3;
    const int FILTER_VEHICLE_TWO_NO              = 4;
    const int FILTER_EVOLUTION_ALL               = 0;
    const int FILTER_EVOLUTION_YES               = 1;
    const int FILTER_EVOLUTION_YES_WITH_OTHERS   = 2;
    const int FILTER_EVOLUTION_NO_WITH_OTHERS    = 3;
    const int FILTER_EVOLUTION_NO                = 4;

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    private ?VehicleManufacturer $vehicleManufacturer = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    private ?VehicleDrivetrain $vehicleDrivetrain = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    private ?TitleUpdate $titleUpdate = null;

    #[ORM\Column(length: 512, unique: true, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $realLifeVehicle = null;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPercentage = null;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $optimized = false;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $evolution = false;

    #[ORM\Column(nullable: true)]
    private ?float $topSpeedMiles = null;

    #[ORM\Column(nullable: true)]
    private ?float $topSpeedKilometers = null;

    #[ORM\Column(nullable: true)]
    private ?int $seats = null;

    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\Column(nullable: true)]
    private ?int $gears = null;

    #[ORM\Column(nullable: true)]
    private ?float $topSpeedMilesMaximum = null;

    #[ORM\Column(nullable: true)]
    private ?float $topSpeedKilometersMaximum = null;

    #[ORM\Column(nullable: true)]
    private ?float $speed = null;

    #[ORM\Column(nullable: true)]
    private ?float $acceleration = null;

    #[ORM\Column(nullable: true)]
    private ?float $braking = null;

    #[ORM\Column(nullable: true)]
    private ?float $handling = null;

    #[Assert\Image(maxSize: 10000000)]
    private ?UploadedFile $file = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $path = null;

    public string $temp;

    #[ORM\OneToMany(targetEntity: HeistCasinoDriverVehicle::class, mappedBy: 'vehicle')]
    public Collection $heistCasinoDriverVehicles {
        get {
            return $this->heistCasinoDriverVehicles;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleDeliveryMethod::class, mappedBy: 'vehicle')]
    public Collection $vehicleDeliveryMethods {
        get {
            return $this->vehicleDeliveryMethods;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleLocale::class, mappedBy: 'vehicle')]
    public Collection $vehicleLocales {
        get {
            return $this->vehicleLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleModificationLocation::class, mappedBy: 'vehicle')]
    public Collection $vehicleModificationLocations {
        get {
            return $this->vehicleModificationLocations;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleStorageLocation::class, mappedBy: 'vehicle')]
    public Collection $vehicleStorageLocations {
        get {
            return $this->vehicleStorageLocations;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleUser::class, mappedBy: 'vehicle')]
    public Collection $vehicleUsers {
        get {
            return $this->vehicleUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicleAcquisition::class, mappedBy: 'vehicle')]
    public Collection $vehicleVehicleAcquisitions {
        get {
            return $this->vehicleVehicleAcquisitions;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicleClass::class, mappedBy: 'vehicle')]
    public Collection $vehicleVehicleClasses {
        get {
            return $this->vehicleVehicleClasses;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicle::class, mappedBy: 'vehicleOne')]
    public Collection $vehicleVehicleOnes {
        get {
            return $this->vehicleVehicleOnes;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicle::class, mappedBy: 'vehicleTwo')]
    public Collection $vehicleVehicleTwos {
        get {
            return $this->vehicleVehicleTwos;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->heistCasinoDriverVehicles    = new ArrayCollection();
        $this->vehicleDeliveryMethods       = new ArrayCollection();
        $this->vehicleLocales               = new ArrayCollection();
        $this->vehicleModificationLocations = new ArrayCollection();
        $this->vehicleStorageLocations      = new ArrayCollection();
        $this->vehicleUsers                 = new ArrayCollection();
        $this->vehicleVehicleAcquisitions   = new ArrayCollection();
        $this->vehicleVehicleClasses        = new ArrayCollection();
        $this->vehicleVehicleOnes           = new ArrayCollection();
        $this->vehicleVehicleTwos           = new ArrayCollection();
    }

    public function getVehicleManufacturer(): ?VehicleManufacturer
    {
        return $this->vehicleManufacturer;
    }

    public function setVehicleManufacturer(?VehicleManufacturer $vehicleManufacturer): static
    {
        $this->vehicleManufacturer = $vehicleManufacturer;

        return $this;
    }

    public function getVehicleDrivetrain(): ?VehicleDrivetrain
    {
        return $this->vehicleDrivetrain;
    }

    public function setVehicleDrivetrain(?VehicleDrivetrain $vehicleDrivetrain): static
    {
        $this->vehicleDrivetrain = $vehicleDrivetrain;

        return $this;
    }

    public function getTitleUpdate(): ?TitleUpdate
    {
        return $this->titleUpdate;
    }

    public function setTitleUpdate(TitleUpdate $titleUpdate): static
    {
        $this->titleUpdate = $titleUpdate;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRealLifeVehicle(): ?string
    {
        return $this->realLifeVehicle;
    }

    public function setRealLifeVehicle(?string $realLifeVehicle): static
    {
        $this->realLifeVehicle = $realLifeVehicle;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDiscountedPrice(): ?int
    {
        return $this->discountedPrice;
    }

    public function setDiscountedPrice(?int $discountedPrice): static
    {
        $this->discountedPrice = $discountedPrice;

        return $this;
    }

    public function getDiscountedPercentage(): ?int
    {
        return $this->discountedPercentage;
    }

    public function setDiscountedPercentage(?int $discountedPercentage): static
    {
        $this->discountedPercentage = $discountedPercentage;

        return $this;
    }

    public function isOptimized(): ?bool
    {
        return $this->optimized;
    }

    public function setOptimized(bool $optimized): static
    {
        $this->optimized = $optimized;

        return $this;
    }

    public function isEvolution(): ?bool
    {
        return $this->evolution;
    }

    public function setEvolution(bool $evolution): static
    {
        $this->evolution = $evolution;

        return $this;
    }

    public function getTopSpeedMiles(): ?float
    {
        return $this->topSpeedMiles;
    }

    public function setTopSpeedMiles(?float $topSpeedMiles): static
    {
        $this->topSpeedMiles = $topSpeedMiles;

        return $this;
    }

    public function getTopSpeedKilometers(): ?float
    {
        return $this->topSpeedKilometers;
    }

    public function setTopSpeedKilometers(?float $topSpeedKilometers): static
    {
        $this->topSpeedKilometers = $topSpeedKilometers;

        return $this;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function setSeats(?int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getGears(): ?int
    {
        return $this->gears;
    }

    public function setGears(?int $gears): static
    {
        $this->gears = $gears;

        return $this;
    }

    public function getTopSpeedMilesMaximum(): ?float
    {
        return $this->topSpeedMilesMaximum;
    }

    public function setTopSpeedMilesMaximum(?float $topSpeedMilesMaximum): static
    {
        $this->topSpeedMilesMaximum = $topSpeedMilesMaximum;

        return $this;
    }

    public function getTopSpeedKilometersMaximum(): ?float
    {
        return $this->topSpeedKilometersMaximum;
    }

    public function setTopSpeedKilometersMaximum(?float $topSpeedKilometersMaximum): static
    {
        $this->topSpeedKilometersMaximum = $topSpeedKilometersMaximum;

        return $this;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function setSpeed(?float $speed): static
    {
        $this->speed = $speed;

        return $this;
    }

    public function getAcceleration(): ?float
    {
        return $this->acceleration;
    }

    public function setAcceleration(?float $acceleration): static
    {
        $this->acceleration = $acceleration;

        return $this;
    }

    public function getBraking(): ?float
    {
        return $this->braking;
    }

    public function setBraking(?float $braking): static
    {
        $this->braking = $braking;

        return $this;
    }

    public function getHandling(): ?float
    {
        return $this->handling;
    }

    public function setHandling(?float $handling): static
    {
        $this->handling = $handling;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): static
    {
        $this->file = $file;
        if ($this->path !== null) {
            $this->temp = $this->path;
            $this->path = null;
        } else {
            $this->path = 'initial';
            $this->temp = null;
        }

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }
}

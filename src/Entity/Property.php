<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'property')]
class Property
{
    const string PERMISSION_PROPERTY_VIEW          = 'PROPERTY_VIEW';
    const string PERMISSION_PROPERTY_ADD           = 'PROPERTY_ADD';
    const string PERMISSION_PROPERTY_EDIT          = 'PROPERTY_EDIT';
    const string PERMISSION_PROPERTY_CONFIGURATION = 'PROPERTY_CONFIGURATION';
    const string PERMISSION_PROPERTY_OWN           = 'PROPERTY_OWN';
    const string PERMISSION_PROPERTY_DELETE        = 'PROPERTY_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?PropertyType $propertyType = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?Business $business = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?PropertyArea $propertyArea = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?PropertyLocation $propertyLocation = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?TitleUpdate $titleUpdate = null;

    #[ORM\Column(length: 512, unique: true, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(nullable: false)]
    private int $price;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPercentage = null;

    #[ORM\Column(nullable: true)]
    private ?int $capacityVehicles = null;

    #[ORM\Column(nullable: true)]
    private ?int $capacityBicycles = null;

    #[Assert\Image(maxSize: 10000000)]
    private ?UploadedFile $file = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $path = null;

    public string $temp;

    #[ORM\OneToMany(targetEntity: PropertyLocale::class, mappedBy: 'property')]
    public Collection $propertyLocales {
        get {
            return $this->propertyLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyPropertyAcquisition::class, mappedBy: 'property')]
    public Collection $propertyPropertyAcquisitions {
        get {
            return $this->propertyPropertyAcquisitions;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyProperty::class, mappedBy: 'propertyOne')]
    public Collection $propertyPropertyOnes {
        get {
            return $this->propertyPropertyOnes;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyProperty::class, mappedBy: 'propertyTwo')]
    public Collection $propertyPropertyTwos {
        get {
            return $this->propertyPropertyTwos;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyUser::class, mappedBy: 'property')]
    public Collection $propertyUsers {
        get {
            return $this->propertyUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleUser::class, mappedBy: 'property')]
    public Collection $vehicleUsers {
        get {
            return $this->vehicleUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleUser::class, mappedBy: 'wantedProperty')]
    public Collection $wantedVehicleUsers {
        get {
            return $this->wantedVehicleUsers;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->propertyLocales              = new ArrayCollection();
        $this->propertyPropertyAcquisitions = new ArrayCollection();
        $this->propertyPropertyOnes         = new ArrayCollection();
        $this->propertyPropertyTwos         = new ArrayCollection();
        $this->propertyUsers                = new ArrayCollection();
        $this->vehicleUsers                 = new ArrayCollection();
        $this->wantedVehicleUsers           = new ArrayCollection();
    }

    public function getPropertyType(): ?PropertyType
    {
        return $this->propertyType;
    }

    public function setPropertyType(?PropertyType $propertyType): static
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(?Business $business): static
    {
        $this->business = $business;

        return $this;
    }

    public function getPropertyArea(): ?PropertyArea
    {
        return $this->propertyArea;
    }

    public function setPropertyArea(?PropertyArea $propertyArea): static
    {
        $this->propertyArea = $propertyArea;

        return $this;
    }

    public function getPropertyLocation(): ?PropertyLocation
    {
        return $this->propertyLocation;
    }

    public function setPropertyLocation(?PropertyLocation $propertyLocation): static
    {
        $this->propertyLocation = $propertyLocation;

        return $this;
    }

    public function getTitleUpdate(): ?TitleUpdate
    {
        return $this->titleUpdate;
    }

    public function setTitleUpdate(?TitleUpdate $titleUpdate): static
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
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

    public function getCapacityVehicles(): ?int
    {
        return $this->capacityVehicles;
    }

    public function setCapacityVehicles(?int $capacityVehicles): static
    {
        $this->capacityVehicles = $capacityVehicles;

        return $this;
    }

    public function getCapacityBicycles(): ?int
    {
        return $this->capacityBicycles;
    }

    public function setCapacityBicycles(?int $capacityBicycles): static
    {
        $this->capacityBicycles = $capacityBicycles;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): static
    {
        $this->file = $file;
        // check if we have an old image path
        if ($this->path !== null) {
            // store the old name to delete after the update
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

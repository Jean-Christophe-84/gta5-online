<?php

namespace App\Entity;

use App\Enum\SpeedUnit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'locale')]
class Locale
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $slug;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $code;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $default = false;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $media = false;

    #[ORM\Column(length: 256, nullable: false)]
    private string $extension;

    #[ORM\Column(nullable: false, enumType: SpeedUnit::class, options: ['default' => SpeedUnit::KILOMETERS])]
    private SpeedUnit $speedUnit = SpeedUnit::KILOMETERS;

    #[ORM\OneToMany(targetEntity: BountyMissionLocale::class, mappedBy: 'locale')]
    public Collection $bountyMissionLocales {
        get {
            return $this->bountyMissionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: BusinessGoodLocale::class, mappedBy: 'locale')]
    public Collection $businessGoodLocales {
        get {
            return $this->businessGoodLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: BusinessLocale::class, mappedBy: 'locale')]
    public Collection $businessLocales {
        get {
            return $this->businessLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: CustomizationLocale::class, mappedBy: 'locale')]
    public Collection $customizationLocales {
        get {
            return $this->customizationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: DeliveryMethodLocale::class, mappedBy: 'locale')]
    public Collection $deliveryMethodLocales {
        get {
            return $this->deliveryMethodLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: GroupGtaLocale::class, mappedBy: 'locale')]
    public Collection $groupGtaLocales {
        get {
            return $this->groupGtaLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistCasinoMainLootLocale::class, mappedBy: 'locale')]
    public Collection $heistCasinoMainLootLocales {
        get {
            return $this->heistCasinoMainLootLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistCayoPericoMainLootLocale::class, mappedBy: 'locale')]
    public Collection $heistCayoPericoMainLootLocales {
        get {
            return $this->heistCayoPericoMainLootLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistKortzCenterMainLootLocale::class, mappedBy: 'locale')]
    public Collection $heistKortzCenterMainLootLocales {
        get {
            return $this->heistKortzCenterMainLootLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistKortzCenterSecondaryLootNameLocale::class, mappedBy: 'locale')]
    public Collection $heistKortzCenterSecondaryLootNameLocales {
        get {
            return $this->heistKortzCenterSecondaryLootNameLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: ModificationLocationLocale::class, mappedBy: 'locale')]
    public Collection $modificationLocationLocales {
        get {
            return $this->modificationLocationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyAcquisitionLocale::class, mappedBy: 'locale')]
    public Collection $propertyAcquisitionLocales {
        get {
            return $this->propertyAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyLocale::class, mappedBy: 'locale')]
    public Collection $propertyLocales {
        get {
            return $this->propertyLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyTypeLocale::class, mappedBy: 'locale')]
    public Collection $propertyTypeLocales {
        get {
            return $this->propertyTypeLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: StorageLocationLocale::class, mappedBy: 'locale')]
    public Collection $storageLocationLocales {
        get {
            return $this->storageLocationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'locale')]
    public Collection $users {
        get {
            return $this->users;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleAcquisitionLocale::class, mappedBy: 'locale')]
    public Collection $vehicleAcquisitionLocales {
        get {
            return $this->vehicleAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleClassLocale::class, mappedBy: 'locale')]
    public Collection $vehicleClassLocales {
        get {
            return $this->vehicleClassLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleDrivetrainLocale::class, mappedBy: 'locale')]
    public Collection $vehicleDrivetrainLocales {
        get {
            return $this->vehicleDrivetrainLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleLocale::class, mappedBy: 'locale')]
    public Collection $vehicleLocales {
        get {
            return $this->vehicleLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponAcquisitionLocale::class, mappedBy: 'locale')]
    public Collection $weaponAcquisitionLocales {
        get {
            return $this->weaponAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponClassLocale::class, mappedBy: 'locale')]
    public Collection $weaponClassLocales {
        get {
            return $this->weaponClassLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponLocale::class, mappedBy: 'locale')]
    public Collection $weaponLocales {
        get {
            return $this->weaponLocales;
        }
    }

    public function __construct()
    {
        $this->bountyMissionLocales                     = new ArrayCollection();
        $this->businessGoodLocales                      = new ArrayCollection();
        $this->businessLocales                          = new ArrayCollection();
        $this->customizationLocales                     = new ArrayCollection();
        $this->deliveryMethodLocales                    = new ArrayCollection();
        $this->groupGtaLocales                          = new ArrayCollection();
        $this->heistCasinoMainLootLocales               = new ArrayCollection();
        $this->heistCayoPericoMainLootLocales           = new ArrayCollection();
        $this->heistKortzCenterMainLootLocales          = new ArrayCollection();
        $this->heistKortzCenterSecondaryLootNameLocales = new ArrayCollection();
        $this->modificationLocationLocales              = new ArrayCollection();
        $this->propertyAcquisitionLocales               = new ArrayCollection();
        $this->propertyLocales                          = new ArrayCollection();
        $this->propertyTypeLocales                      = new ArrayCollection();
        $this->storageLocationLocales                   = new ArrayCollection();
        $this->users                                    = new ArrayCollection();
        $this->vehicleAcquisitionLocales                = new ArrayCollection();
        $this->vehicleClassLocales                      = new ArrayCollection();
        $this->vehicleDrivetrainLocales                 = new ArrayCollection();
        $this->vehicleLocales                           = new ArrayCollection();
        $this->weaponAcquisitionLocales                 = new ArrayCollection();
        $this->weaponClassLocales                       = new ArrayCollection();
        $this->weaponLocales                            = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getDefault(): ?bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function getMedia(): ?bool
    {
        return $this->media;
    }

    public function setMedia(bool $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getSpeedUnit(): SpeedUnit
    {
        return $this->speedUnit;
    }

    public function setSpeedUnit(SpeedUnit $speedUnit): self
    {
        $this->speedUnit = $speedUnit;

        return $this;
    }
}

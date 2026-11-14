<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'weapon')]
class Weapon
{
    const string PERMISSION_WEAPON_VIEW          = 'WEAPON_VIEW';
    const string PERMISSION_WEAPON_ADD           = 'WEAPON_ADD';
    const string PERMISSION_WEAPON_EDIT          = 'WEAPON_EDIT';
    const string PERMISSION_WEAPON_CONFIGURATION = 'WEAPON_CONFIGURATION';
    const string PERMISSION_WEAPON_OWN           = 'WEAPON_OWN';
    const string PERMISSION_WEAPON_DELETE        = 'WEAPON_DELETE';

    const int FILTER_WEAPON_TWO_ALL             = 0;
    const int FILTER_WEAPON_TWO_YES             = 1;
    const int FILTER_WEAPON_TWO_YES_WITH_OTHERS = 2;
    const int FILTER_WEAPON_TWO_NO_WITH_OTHERS  = 3;
    const int FILTER_WEAPON_TWO_NO              = 4;
    const int FILTER_EVOLUTION_ALL              = 0;
    const int FILTER_EVOLUTION_YES              = 1;
    const int FILTER_EVOLUTION_YES_WITH_OTHERS  = 2;
    const int FILTER_EVOLUTION_NO_WITH_OTHERS   = 3;
    const int FILTER_EVOLUTION_NO               = 4;

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'weapons')]
    private ?WeaponManufacturer $weaponManufacturer = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'weapons')]
    private ?TitleUpdate $titleUpdate = null;

    #[ORM\Column(length: 512, unique: true, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $realLifeWeapon = null;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountedPercentage = null;

    #[ORM\Column(nullable: true)]
    private ?int $unlockRank = null;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $evolution = false;

    #[ORM\Column(nullable: true)]
    private ?float $damage = null;

    #[ORM\Column(nullable: true)]
    private ?float $fireRate = null;

    #[ORM\Column(nullable: true)]
    private ?float $accuracy = null;

    #[ORM\Column(nullable: true)]
    private ?float $rangeWeapon = null;

    #[ORM\Column(nullable: true)]
    private ?float $clipSize = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxAmmo = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxAmmoUpgrade = null;

    #[Assert\Image(maxSize: 10000000)]
    private ?UploadedFile $file = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $path = null;

    public string $temp;

    #[ORM\OneToMany(targetEntity: HeistCasinoGunmanWeapon::class, mappedBy: 'weapon')]
    public Collection $heistCasinoGunmanWeapons {
        get {
            return $this->heistCasinoGunmanWeapons;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponCustomization::class, mappedBy: 'weapon')]
    public Collection $weaponCustomizations {
        get {
            return $this->weaponCustomizations;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponLocale::class, mappedBy: 'weapon')]
    public Collection $weaponLocales {
        get {
            return $this->weaponLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponUser::class, mappedBy: 'weapon')]
    public Collection $weaponUsers {
        get {
            return $this->weaponUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeaponAcquisition::class, mappedBy: 'weapon')]
    public Collection $weaponWeaponAcquisitions {
        get {
            return $this->weaponWeaponAcquisitions;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeaponClass::class, mappedBy: 'weapon')]
    public Collection $weaponWeaponClasses {
        get {
            return $this->weaponWeaponClasses;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeapon::class, mappedBy: 'weaponOne')]
    public Collection $weaponWeaponOnes {
        get {
            return $this->weaponWeaponOnes;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeapon::class, mappedBy: 'weaponTwo')]
    public Collection $weaponWeaponTwos {
        get {
            return $this->weaponWeaponTwos;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->heistCasinoGunmanWeapons = new ArrayCollection();
        $this->weaponCustomizations     = new ArrayCollection();
        $this->weaponLocales            = new ArrayCollection();
        $this->weaponUsers              = new ArrayCollection();
        $this->weaponWeaponAcquisitions = new ArrayCollection();
        $this->weaponWeaponClasses      = new ArrayCollection();
        $this->weaponWeaponOnes         = new ArrayCollection();
        $this->weaponWeaponTwos         = new ArrayCollection();
    }

    public function getWeaponManufacturer(): ?WeaponManufacturer
    {
        return $this->weaponManufacturer;
    }

    public function setWeaponManufacturer(?WeaponManufacturer $weaponManufacturer): static
    {
        $this->weaponManufacturer = $weaponManufacturer;

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

    public function getRealLifeWeapon(): ?string
    {
        return $this->realLifeWeapon;
    }

    public function setRealLifeWeapon(?string $realLifeWeapon): static
    {
        $this->realLifeWeapon = $realLifeWeapon;

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

    public function getUnlockRank(): ?int
    {
        return $this->unlockRank;
    }

    public function setUnlockRank(?int $unlockRank): static
    {
        $this->unlockRank = $unlockRank;

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

    public function getDamage(): ?float
    {
        return $this->damage;
    }

    public function setDamage(?float $damage): static
    {
        $this->damage = $damage;

        return $this;
    }

    public function getFireRate(): ?float
    {
        return $this->fireRate;
    }

    public function setFireRate(?float $fireRate): static
    {
        $this->fireRate = $fireRate;

        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(?float $accuracy): static
    {
        $this->accuracy = $accuracy;

        return $this;
    }

    public function getRangeWeapon(): ?float
    {
        return $this->rangeWeapon;
    }

    public function setRangeWeapon(?float $rangeWeapon): static
    {
        $this->rangeWeapon = $rangeWeapon;

        return $this;
    }

    public function getClipSize(): ?float
    {
        return $this->clipSize;
    }

    public function setClipSize(?float $clipSize): static
    {
        $this->clipSize = $clipSize;

        return $this;
    }

    public function getMaxAmmo(): ?int
    {
        return $this->maxAmmo;
    }

    public function setMaxAmmo(?int $maxAmmo): static
    {
        $this->maxAmmo = $maxAmmo;

        return $this;
    }

    public function getMaxAmmoUpgrade(): ?int
    {
        return $this->maxAmmoUpgrade;
    }

    public function setMaxAmmoUpgrade(?int $maxAmmoUpgrade): static
    {
        $this->maxAmmoUpgrade = $maxAmmoUpgrade;

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

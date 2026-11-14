<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_acquisition')]
class WeaponAcquisition
{
    const string PERMISSION_WEAPON_ACQUISITION_VIEW   = 'WEAPON_ACQUISITION_VIEW';
    const string PERMISSION_WEAPON_ACQUISITION_ADD    = 'WEAPON_ACQUISITION_ADD';
    const string PERMISSION_WEAPON_ACQUISITION_EDIT   = 'WEAPON_ACQUISITION_EDIT';
    const string PERMISSION_WEAPON_ACQUISITION_DELETE = 'WEAPON_ACQUISITION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $promotion = false;

    #[ORM\OneToMany(targetEntity: WeaponAcquisitionLocale::class, mappedBy: 'weaponAcquisition')]
    public Collection $weaponAcquisitionLocales {
        get {
            return $this->weaponAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeaponAcquisition::class, mappedBy: 'weaponAcquisition')]
    public Collection $weaponWeaponAcquisitions {
        get {
            return $this->weaponWeaponAcquisitions;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->weaponAcquisitionLocales = new ArrayCollection();
        $this->weaponWeaponAcquisitions = new ArrayCollection();
    }

    public function isPromotion(): bool
    {
        return $this->promotion;
    }

    public function setPromotion(bool $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }
}

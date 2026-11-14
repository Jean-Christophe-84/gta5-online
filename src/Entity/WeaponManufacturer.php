<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_manufacturer')]
class WeaponManufacturer
{
    const string PERMISSION_WEAPON_MANUFACTURER_VIEW   = 'WEAPON_MANUFACTURER_VIEW';
    const string PERMISSION_WEAPON_MANUFACTURER_ADD    = 'WEAPON_MANUFACTURER_ADD';
    const string PERMISSION_WEAPON_MANUFACTURER_EDIT   = 'WEAPON_MANUFACTURER_EDIT';
    const string PERMISSION_WEAPON_MANUFACTURER_DELETE = 'WEAPON_MANUFACTURER_DELETE';

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

    #[ORM\OneToMany(targetEntity: Weapon::class, mappedBy: 'weaponManufacturer')]
    public Collection $weapons {
        get {
            return $this->weapons;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->weapons = new ArrayCollection();
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
}

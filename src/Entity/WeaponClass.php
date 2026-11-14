<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_class')]
class WeaponClass
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    #[ORM\OneToMany(targetEntity: WeaponClassLocale::class, mappedBy: 'weaponClass')]
    public Collection $weaponClassLocales {
        get {
            return $this->weaponClassLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponWeaponClass::class, mappedBy: 'weaponClass')]
    public Collection $weaponWeaponClasses {
        get {
            return $this->weaponWeaponClasses;
        }
    }

    public function __construct()
    {
        $this->weaponClassLocales  = new ArrayCollection();
        $this->weaponWeaponClasses = new ArrayCollection();
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}

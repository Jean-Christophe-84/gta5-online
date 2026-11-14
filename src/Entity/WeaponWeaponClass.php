<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_weapon_class')]
class WeaponWeaponClass
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponClasses')]
    private Weapon $weapon;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponClasses')]
    private WeaponClass $weaponClass;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getWeapon(): ?Weapon
    {
        return $this->weapon;
    }

    public function setWeapon(Weapon $weapon): static
    {
        $this->weapon = $weapon;

        return $this;
    }

    public function getWeaponClass(): ?WeaponClass
    {
        return $this->weaponClass;
    }

    public function setWeaponClass(WeaponClass $weaponClass): static
    {
        $this->weaponClass = $weaponClass;

        return $this;
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

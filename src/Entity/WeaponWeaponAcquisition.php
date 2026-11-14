<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_weapon_acquisition')]
class WeaponWeaponAcquisition
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponAcquisitions')]
    private Weapon $weapon;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponAcquisitions')]
    private WeaponAcquisition $weaponAcquisition;

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

    public function getWeaponAcquisition(): ?WeaponAcquisition
    {
        return $this->weaponAcquisition;
    }

    public function setWeaponAcquisition(WeaponAcquisition $weaponAcquisition): static
    {
        $this->weaponAcquisition = $weaponAcquisition;

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

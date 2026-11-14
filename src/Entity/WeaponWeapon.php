<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_weapon')]
class WeaponWeapon
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponOnes')]
    private Weapon $weaponOne;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponWeaponTwos')]
    private Weapon $weaponTwo;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getWeaponOne(): ?Weapon
    {
        return $this->weaponOne;
    }

    public function setWeaponOne(Weapon $weaponOne): static
    {
        $this->weaponOne = $weaponOne;

        return $this;
    }

    public function getWeaponTwo(): ?Weapon
    {
        return $this->weaponTwo;
    }

    public function setWeaponTwo(Weapon $weaponTwo): static
    {
        $this->weaponTwo = $weaponTwo;

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

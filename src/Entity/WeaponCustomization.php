<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_customization')]
class WeaponCustomization
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponCustomizations')]
    private Weapon $weapon;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'weaponCustomizations')]
    #[ORM\JoinColumn(nullable: false)]
    private Customization $customization;

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

    public function getCustomization(): ?Customization
    {
        return $this->customization;
    }

    public function setCustomization(Customization $customization): static
    {
        $this->customization = $customization;

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

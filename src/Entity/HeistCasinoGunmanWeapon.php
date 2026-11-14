<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\HeistCasinoApproach;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_gunman_weapon')]
class HeistCasinoGunmanWeapon
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoGunmanWeapons')]
    private HeistCasinoGunman $heistCasinoGunman;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoGunmanWeapons')]
    private Weapon $weapon;

    #[ORM\Id]
    #[ORM\Column(nullable: false, enumType: HeistCasinoApproach::class)]
    private ?HeistCasinoApproach $approach = null;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    public function getHeistCasinoGunman(): ?HeistCasinoGunman
    {
        return $this->heistCasinoGunman;
    }

    public function setHeistCasinoGunman(HeistCasinoGunman $heistCasinoGunman): static
    {
        $this->heistCasinoGunman = $heistCasinoGunman;

        return $this;
    }

    public function getWeapon(): ?Weapon
    {
        return $this->weapon;
    }

    public function setWeapon(Weapon $weapon): static
    {
        $this->weapon = $weapon;

        return $this;
    }

    public function getApproach(): ?HeistCasinoApproach
    {
        return $this->approach;
    }

    public function setApproach(?HeistCasinoApproach $approach): static
    {
        $this->approach = $approach;

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

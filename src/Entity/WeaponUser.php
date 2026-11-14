<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_user')]
class WeaponUser
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'weaponUsers')]
    // @note: nullable=false by default
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'weaponUsers')]
    // @note: nullable=false by default
    private Weapon $weapon;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $owned = false;

    use InfoUpdateTrait;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getWeapon(): ?Weapon
    {
        return $this->weapon;
    }

    public function setWeapon(?Weapon $weapon): static
    {
        $this->weapon = $weapon;

        return $this;
    }

    public function isOwned(): ?bool
    {
        return $this->owned;
    }

    public function setOwned(bool $owned): static
    {
        $this->owned = $owned;

        return $this;
    }
}

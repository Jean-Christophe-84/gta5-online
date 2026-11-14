<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class WeaponLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponLocales')]
    private Weapon $weapon;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $unlockWeapon = null;


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

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getUnlockWeapon(): ?string
    {
        return $this->unlockWeapon;
    }

    public function setUnlockWeapon(?string $unlockWeapon): static
    {
        $this->unlockWeapon = $unlockWeapon;

        return $this;
    }
}

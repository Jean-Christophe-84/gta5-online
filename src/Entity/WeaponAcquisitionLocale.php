<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'weapon_acquisition_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class WeaponAcquisitionLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponAcquisitionLocales')]
    private WeaponAcquisition $weaponAcquisition;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'weaponAcquisitionLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    use InfoUpdateTrait;

    public function getWeaponAcquisition(): ?WeaponAcquisition
    {
        return $this->weaponAcquisition;
    }

    public function setWeaponAcquisition(WeaponAcquisition $weaponAcquisition): static
    {
        $this->weaponAcquisition = $weaponAcquisition;

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
}

<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_class_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class VehicleClassLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleClassLocales')]
    private VehicleClass $vehicleClass;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'vehicleClassLocales')]
    #[ORM\JoinColumn(nullable: false)]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getVehicleClass(): ?VehicleClass
    {
        return $this->vehicleClass;
    }

    public function setVehicleClass(VehicleClass $vehicleClass): static
    {
        $this->vehicleClass = $vehicleClass;

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

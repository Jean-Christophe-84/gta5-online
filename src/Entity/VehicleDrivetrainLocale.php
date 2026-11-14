<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_drivetrain_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class VehicleDrivetrainLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleDrivetrainLocales')]
    private VehicleDrivetrain $vehicleDrivetrain;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleDrivetrainLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getVehicleDrivetrain(): ?VehicleDrivetrain
    {
        return $this->vehicleDrivetrain;
    }

    public function setVehicleDrivetrain(VehicleDrivetrain $vehicleDrivetrain): static
    {
        $this->vehicleDrivetrain = $vehicleDrivetrain;

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

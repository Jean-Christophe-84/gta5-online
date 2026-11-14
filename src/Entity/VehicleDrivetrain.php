<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_drivetrain')]
class VehicleDrivetrain
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'vehicleDrivetrain')]
    public Collection $vehicles {
        get {
            return $this->vehicles;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleDrivetrainLocale::class, mappedBy: 'vehicleDrivetrain')]
    public Collection $vehicleDrivetrainLocales {
        get {
            return $this->vehicleDrivetrainLocales;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private string $shortName;

    public function __construct()
    {
        $this->vehicles                 = new ArrayCollection();
        $this->vehicleDrivetrainLocales = new ArrayCollection();
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): static
    {
        $this->shortName = $shortName;

        return $this;
    }
}

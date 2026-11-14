<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_class')]
class VehicleClass
{
    const int BICYCLES_ID = 9;

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleClassLocale::class, mappedBy: 'vehicleClass')]
    public Collection $vehicleClassLocales {
        get {
            return $this->vehicleClassLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicleClass::class, mappedBy: 'vehicleClass')]
    public Collection $vehicleVehicleClasses {
        get {
            return $this->vehicleVehicleClasses;
        }
    }

    public function __construct()
    {
        $this->vehicleClassLocales   = new ArrayCollection();
        $this->vehicleVehicleClasses = new ArrayCollection();
    }
}

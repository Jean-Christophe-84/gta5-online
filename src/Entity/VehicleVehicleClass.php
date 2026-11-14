<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_vehicle_class')]
class VehicleVehicleClass
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleClasses')]
    private Vehicle $vehicle;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleClasses')]
    private VehicleClass $vehicleClass;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getVehicleClass(): ?VehicleClass
    {
        return $this->vehicleClass;
    }

    public function setVehicleClass(VehicleClass $vehicleClass): static
    {
        $this->vehicleClass = $vehicleClass;

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

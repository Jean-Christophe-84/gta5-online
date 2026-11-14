<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_vehicle_acquisition')]
class VehicleVehicleAcquisition
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleAcquisitions')]
    private Vehicle $vehicle;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleAcquisitions')]
    private VehicleAcquisition $vehicleAcquisition;

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

    public function getVehicleAcquisition(): ?VehicleAcquisition
    {
        return $this->vehicleAcquisition;
    }

    public function setVehicleAcquisition(VehicleAcquisition $vehicleAcquisition): static
    {
        $this->vehicleAcquisition = $vehicleAcquisition;

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

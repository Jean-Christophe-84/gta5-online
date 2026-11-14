<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_vehicle')]
class VehicleVehicle
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleOnes')]
    private Vehicle $vehicleOne;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleVehicleTwos')]
    private Vehicle $vehicleTwo;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getVehicleOne(): ?Vehicle
    {
        return $this->vehicleOne;
    }

    public function setVehicleOne(Vehicle $vehicleOne): static
    {
        $this->vehicleOne = $vehicleOne;

        return $this;
    }

    public function getVehicleTwo(): ?Vehicle
    {
        return $this->vehicleTwo;
    }

    public function setVehicleTwo(Vehicle $vehicleTwo): static
    {
        $this->vehicleTwo = $vehicleTwo;

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

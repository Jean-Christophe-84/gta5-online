<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_driver_vehicle')]
class HeistCasinoDriverVehicle
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoDriverVehicles')]
    private HeistCasinoDriver $heistCasinoDriver;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoDriverVehicles')]
    private Vehicle $vehicle;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    public function getHeistCasinoDriver(): ?HeistCasinoDriver
    {
        return $this->heistCasinoDriver;
    }

    public function setHeistCasinoDriver(HeistCasinoDriver $heistCasinoDriver): static
    {
        $this->heistCasinoDriver = $heistCasinoDriver;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

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

<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_driver')]
class HeistCasinoDriver
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(nullable: false)]
    private ?int $percent = null;

    #[ORM\OneToMany(targetEntity: HeistCasinoDriverVehicle::class, mappedBy: 'heistCasinoDriver')]
    public Collection $heistCasinoDriverVehicles {
        get {
            return $this->heistCasinoDriverVehicles;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistCasino::class, mappedBy: 'driver')]
    public Collection $heistCasinos {
        get {
            return $this->heistCasinos;
        }
    }

    public function __construct()
    {
        $this->heistCasinoDriverVehicles = new ArrayCollection();
        $this->heistCasinos              = new ArrayCollection();
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

    public function getPercent(): ?int
    {
        return $this->percent;
    }

    public function setPercent(int $percent): static
    {
        $this->percent = $percent;

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_user')]
class VehicleUser
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleUsers')]
    private User $user;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleUsers')]
    private Vehicle $vehicle;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'vehicleUsers')]
    private ?Property $property = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'wantedVehicleUsers')]
    private ?Property $wantedProperty = null;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $owned = false;

    use InfoUpdateTrait;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getWantedProperty(): ?Property
    {
        return $this->wantedProperty;
    }

    public function setWantedProperty(?Property $wantedProperty): static
    {
        $this->wantedProperty = $wantedProperty;

        return $this;
    }

    public function isOwned(): ?bool
    {
        return $this->owned;
    }

    public function setOwned(bool $owned): static
    {
        $this->owned = $owned;

        return $this;
    }
}

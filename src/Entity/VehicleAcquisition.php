<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_acquisition')]
class VehicleAcquisition
{
    const string PERMISSION_VEHICLE_ACQUISITION_VIEW   = 'VEHICLE_ACQUISITION_VIEW';
    const string PERMISSION_VEHICLE_ACQUISITION_ADD    = 'VEHICLE_ACQUISITION_ADD';
    const string PERMISSION_VEHICLE_ACQUISITION_EDIT   = 'VEHICLE_ACQUISITION_EDIT';
    const string PERMISSION_VEHICLE_ACQUISITION_DELETE = 'VEHICLE_ACQUISITION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => true])]
    private bool $acquirable = true;

    #[ORM\Column(nullable: false, options: ['default' => true])]
    private bool $available = true;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $promotion = false;

    #[ORM\OneToMany(targetEntity: VehicleAcquisitionLocale::class, mappedBy: 'vehicleAcquisition')]
    public Collection $vehicleAcquisitionLocales {
        get {
            return $this->vehicleAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleVehicleAcquisition::class, mappedBy: 'vehicleAcquisition')]
    public Collection $vehicleVehicleAcquisitions {
        get {
            return $this->vehicleVehicleAcquisitions;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->vehicleAcquisitionLocales  = new ArrayCollection();
        $this->vehicleVehicleAcquisitions = new ArrayCollection();
    }

    public function isAcquirable(): bool
    {
        return $this->acquirable;
    }

    public function setAcquirable(bool $acquirable): static
    {
        $this->acquirable = $acquirable;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): static
    {
        $this->available = $available;

        return $this;
    }

    public function isPromotion(): bool
    {
        return $this->promotion;
    }

    public function setPromotion(bool $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }
}

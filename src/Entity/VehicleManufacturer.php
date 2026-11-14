<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_manufacturer')]
class VehicleManufacturer
{
    const string PERMISSION_VEHICLE_MANUFACTURER_VIEW   = 'VEHICLE_MANUFACTURER_VIEW';
    const string PERMISSION_VEHICLE_MANUFACTURER_ADD    = 'VEHICLE_MANUFACTURER_ADD';
    const string PERMISSION_VEHICLE_MANUFACTURER_EDIT   = 'VEHICLE_MANUFACTURER_EDIT';
    const string PERMISSION_VEHICLE_MANUFACTURER_DELETE = 'VEHICLE_MANUFACTURER_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $realName = null;

    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'vehicleManufacturer')]
    public Collection $vehicles {
        get {
            return $this->vehicles;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
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

    public function getRealName(): ?string
    {
        return $this->realName;
    }

    public function setRealName(?string $realName): static
    {
        $this->realName = $realName;

        return $this;
    }
}

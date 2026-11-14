<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'storage_location')]
class StorageLocation
{
    const string PERMISSION_STORAGE_LOCATION_VIEW   = 'STORAGE_LOCATION_VIEW';
    const string PERMISSION_STORAGE_LOCATION_ADD    = 'STORAGE_LOCATION_ADD';
    const string PERMISSION_STORAGE_LOCATION_EDIT   = 'STORAGE_LOCATION_EDIT';
    const string PERMISSION_STORAGE_LOCATION_DELETE = 'STORAGE_LOCATION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => true])]
    private bool $propertyRequired = true;

    #[ORM\OneToMany(targetEntity: PropertyTypeStorageLocation::class, mappedBy: 'storageLocation')]
    public Collection $propertyTypeStorageLocations {
        get {
            return $this->propertyTypeStorageLocations;
        }
    }

    #[ORM\OneToMany(targetEntity: StorageLocationLocale::class, mappedBy: 'storageLocation')]
    public Collection $storageLocationLocales {
        get {
            return $this->storageLocationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleStorageLocation::class, mappedBy: 'storageLocation')]
    public Collection $vehicleStorageLocations {
        get {
            return $this->vehicleStorageLocations;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->propertyTypeStorageLocations = new ArrayCollection();
        $this->storageLocationLocales       = new ArrayCollection();
        $this->vehicleStorageLocations      = new ArrayCollection();
    }

    public function isPropertyRequired(): bool
    {
        return $this->propertyRequired;
    }

    public function setPropertyRequired(bool $propertyRequired): static
    {
        $this->propertyRequired = $propertyRequired;

        return $this;
    }
}

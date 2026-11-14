<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_type_storage_location')]
class PropertyTypeStorageLocation
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyTypeStorageLocations')]
    // @note: nullable=false by default
    private PropertyType $propertyType;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyTypeStorageLocations')]
    // @note: nullable=false by default
    private StorageLocation $storageLocation;

    use InfoUpdateTrait;

    public function getPropertyType(): ?PropertyType
    {
        return $this->propertyType;
    }

    public function setPropertyType(PropertyType $propertyType): static
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    public function getStorageLocation(): ?StorageLocation
    {
        return $this->storageLocation;
    }

    public function setStorageLocation(StorageLocation $storageLocation): static
    {
        $this->storageLocation = $storageLocation;

        return $this;
    }
}

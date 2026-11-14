<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_type')]
class PropertyType
{
    const string PERMISSION_PROPERTY_TYPE_VIEW   = 'PROPERTY_TYPE_VIEW';
    const string PERMISSION_PROPERTY_TYPE_ADD    = 'PROPERTY_TYPE_ADD';
    const string PERMISSION_PROPERTY_TYPE_EDIT   = 'PROPERTY_TYPE_EDIT';
    const string PERMISSION_PROPERTY_TYPE_DELETE = 'PROPERTY_TYPE_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: Business::class, mappedBy: 'propertyType')]
    public Collection $businesses {
        get {
            return $this->businesses;
        }
    }

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'propertyType')]
    public Collection $properties {
        get {
            return $this->properties;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyTypeLocale::class, mappedBy: 'propertyType')]
    public Collection $propertyTypeLocales {
        get {
            return $this->propertyTypeLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyTypeStorageLocation::class, mappedBy: 'propertyType')]
    public Collection $propertyTypeStorageLocations {
        get {
            return $this->propertyTypeStorageLocations;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->businesses                   = new ArrayCollection();
        $this->properties                   = new ArrayCollection();
        $this->propertyTypeLocales          = new ArrayCollection();
        $this->propertyTypeStorageLocations = new ArrayCollection();
    }
}

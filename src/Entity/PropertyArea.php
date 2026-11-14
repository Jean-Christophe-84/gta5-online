<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_area')]
class PropertyArea
{
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

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'propertyArea')]
    public Collection $properties {
        get {
            return $this->properties;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyLocation::class, mappedBy: 'propertyArea')]
    public Collection $propertyLocations {
        get {
            return $this->propertyLocations;
        }
    }

    public function __construct()
    {
        $this->properties        = new ArrayCollection();
        $this->propertyLocations = new ArrayCollection();
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
}

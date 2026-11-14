<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_location')]
class PropertyLocation
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyLocations')]
    private PropertyArea $propertyArea;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'propertyLocation')]
    public Collection $properties {
        get {
            return $this->properties;
        }
    }

    public function __construct()
    {
        $this->properties = new ArrayCollection();
    }

    public function getPropertyArea(): ?PropertyArea
    {
        return $this->propertyArea;
    }

    public function setPropertyArea(PropertyArea $propertyArea): static
    {
        $this->propertyArea = $propertyArea;

        return $this;
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

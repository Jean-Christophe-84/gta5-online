<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_property_acquisition')]
class PropertyPropertyAcquisition
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyPropertyAcquisitions')]
    private Property $property;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyPropertyAcquisitions')]
    private PropertyAcquisition $propertyAcquisition;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getPropertyAcquisition(): ?PropertyAcquisition
    {
        return $this->propertyAcquisition;
    }

    public function setPropertyAcquisition(PropertyAcquisition $propertyAcquisition): static
    {
        $this->propertyAcquisition = $propertyAcquisition;

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

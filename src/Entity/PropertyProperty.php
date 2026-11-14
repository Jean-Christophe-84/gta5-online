<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_property')]
class PropertyProperty
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyPropertyOnes')]
    private Property $propertyOne;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyPropertyTwos')]
    private Property $propertyTwo;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $position;

    use InfoUpdateTrait;

    public function getPropertyOne(): ?Property
    {
        return $this->propertyOne;
    }

    public function setPropertyOne(?Property $propertyOne): static
    {
        $this->propertyOne = $propertyOne;

        return $this;
    }

    public function getPropertyTwo(): ?Property
    {
        return $this->propertyTwo;
    }

    public function setPropertyTwo(?Property $propertyTwo): static
    {
        $this->propertyTwo = $propertyTwo;

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

<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_user')]
class PropertyUser
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyUsers')]
    private User $user;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyUsers')]
    private Property $property;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $owned = false;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $wanted;

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

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function isOwned(): ?bool
    {
        return $this->owned;
    }

    public function setOwned(?bool $owned): static
    {
        $this->owned = $owned;

        return $this;
    }

    public function isWanted(): ?bool
    {
        return $this->wanted;
    }

    public function setWanted(bool $wanted): static
    {
        $this->wanted = $wanted;

        return $this;
    }
}

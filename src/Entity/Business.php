<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'business')]
class Business
{
    const string PERMISSION_BUSINESS_VIEW   = 'BUSINESS_VIEW';
    const string PERMISSION_BUSINESS_ADD    = 'BUSINESS_ADD';
    const string PERMISSION_BUSINESS_EDIT   = 'BUSINESS_EDIT';
    const string PERMISSION_BUSINESS_DELETE = 'BUSINESS_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businesses')]
    private TitleUpdate $titleUpdate;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businesses')]
    private ?PropertyType $propertyType = null;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private ?int $maximumNumber = null;

    #[ORM\OneToMany(targetEntity: BusinessGood::class, mappedBy: 'business')]
    public Collection $businessGoods {
        get {
            return $this->businessGoods;
        }
    }

    #[ORM\OneToMany(targetEntity: BusinessLocale::class, mappedBy: 'business')]
    public Collection $businessLocales {
        get {
            return $this->businessLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'business')]
    public Collection $properties {
        get {
            return $this->properties;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->businessGoods   = new ArrayCollection();
        $this->businessLocales = new ArrayCollection();
        $this->properties      = new ArrayCollection();
    }

    public function getTitleUpdate(): ?TitleUpdate
    {
        return $this->titleUpdate;
    }

    public function setTitleUpdate(TitleUpdate $titleUpdate): static
    {
        $this->titleUpdate = $titleUpdate;

        return $this;
    }

    public function getPropertyType(): ?PropertyType
    {
        return $this->propertyType;
    }

    public function setPropertyType(PropertyType $propertyType): static
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    public function getMaximumNumber(): ?int
    {
        return $this->maximumNumber;
    }

    public function setMaximumNumber(int $maximumNumber): static
    {
        $this->maximumNumber = $maximumNumber;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_acquisition')]
class PropertyAcquisition
{
    const string PERMISSION_PROPERTY_ACQUISITION_VIEW   = 'PROPERTY_ACQUISITION_VIEW';
    const string PERMISSION_PROPERTY_ACQUISITION_ADD    = 'PROPERTY_ACQUISITION_ADD';
    const string PERMISSION_PROPERTY_ACQUISITION_EDIT   = 'PROPERTY_ACQUISITION_EDIT';
    const string PERMISSION_PROPERTY_ACQUISITION_DELETE = 'PROPERTY_ACQUISITION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $promotion = false;

    #[ORM\OneToMany(targetEntity: PropertyAcquisitionLocale::class, mappedBy: 'propertyAcquisition')]
    public Collection $propertyAcquisitionLocales {
        get {
            return $this->propertyAcquisitionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyPropertyAcquisition::class, mappedBy: 'propertyAcquisition')]
    public Collection $propertyPropertyAcquisitions {
        get {
            return $this->propertyPropertyAcquisitions;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->propertyAcquisitionLocales   = new ArrayCollection();
        $this->propertyPropertyAcquisitions = new ArrayCollection();
    }

    public function isPromotion(): bool
    {
        return $this->promotion;
    }

    public function setPromotion(bool $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }
}

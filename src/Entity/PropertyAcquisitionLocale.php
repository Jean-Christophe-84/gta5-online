<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'property_acquisition_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class PropertyAcquisitionLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyAcquisitionLocales')]
    private PropertyAcquisition $propertyAcquisition;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyAcquisitionLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getPropertyAcquisition(): ?PropertyAcquisition
    {
        return $this->propertyAcquisition;
    }

    public function setPropertyAcquisition(PropertyAcquisition $propertyAcquisition): static
    {
        $this->propertyAcquisition = $propertyAcquisition;

        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): static
    {
        $this->locale = $locale;

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

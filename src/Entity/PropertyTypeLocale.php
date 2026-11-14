<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_type_locale')]
class PropertyTypeLocale
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
    #[ORM\ManyToOne(inversedBy: 'propertyTypeLocales')]
    private PropertyType $propertyType;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'propertyTypeLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

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

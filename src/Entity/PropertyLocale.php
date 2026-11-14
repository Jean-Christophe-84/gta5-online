<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'property_locale')]
class PropertyLocale
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyLocales')]
    // @note: nullable=false by default
    private Property $property;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'propertyLocales')]
    // @note: nullable=false by default
    private Locale $locale;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    use InfoUpdateTrait;

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }
}

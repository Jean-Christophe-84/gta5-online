<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'business_good_locale')]
#[UniqueEntity(fields: ['locale', 'additionalInformations'])]
class BusinessGoodLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businessGoodLocales')]
    private BusinessGood $businessGood;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businessGoodLocales')]
    private Locale $locale;

    #[ORM\Column(nullable: true)]
    private ?string $additionalInformations = null;

    use InfoUpdateTrait;

    public function getBusinessGood(): ?BusinessGood
    {
        return $this->businessGood;
    }

    public function setBusinessGood(BusinessGood $businessGood): static
    {
        $this->businessGood = $businessGood;

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

    public function getAdditionalInformations(): ?string
    {
        return $this->additionalInformations;
    }

    public function setAdditionalInformations(?string $additionalInformations): static
    {
        $this->additionalInformations = $additionalInformations;

        return $this;
    }
}

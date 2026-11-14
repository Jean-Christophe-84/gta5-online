<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'business_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class BusinessLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businessLocales')]
    private Business $business;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businessLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dailyEarnings = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dailyExpenses = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $improvement = null;

    use InfoUpdateTrait;

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(Business $business): static
    {
        $this->business = $business;

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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getDailyEarnings(): ?string
    {
        return $this->dailyEarnings;
    }

    public function setDailyEarnings(?string $dailyEarnings): static
    {
        $this->dailyEarnings = $dailyEarnings;

        return $this;
    }

    public function getDailyExpenses(): ?string
    {
        return $this->dailyExpenses;
    }

    public function setDailyExpenses(?string $dailyExpenses): static
    {
        $this->dailyExpenses = $dailyExpenses;

        return $this;
    }

    public function getImprovement(): ?string
    {
        return $this->improvement;
    }

    public function setImprovement(?string $improvement): static
    {
        $this->improvement = $improvement;

        return $this;
    }
}

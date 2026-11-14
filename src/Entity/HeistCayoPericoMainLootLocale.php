<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'heist_cayo_perico_main_loot_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class HeistCayoPericoMainLootLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCayoPericoMainLootLocales')]
    private HeistCayoPericoMainLoot $heistCayoPericoMainLoot;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'heistCayoPericoMainLootLocales')]
    #[ORM\JoinColumn(nullable: false)]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getHeistCayoPericoMainLoot(): ?HeistCayoPericoMainLoot
    {
        return $this->heistCayoPericoMainLoot;
    }

    public function setHeistCayoPericoMainLoot(HeistCayoPericoMainLoot $heistCayoPericoMainLoot): static
    {
        $this->heistCayoPericoMainLoot = $heistCayoPericoMainLoot;

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

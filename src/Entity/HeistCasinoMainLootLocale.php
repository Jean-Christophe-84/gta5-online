<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_main_loot_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class HeistCasinoMainLootLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoMainLootLocales')]
    private HeistCasinoMainLoot $heistCasinoMainLoot;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'heistCasinoMainLootLocales')]
    #[ORM\JoinColumn(nullable: false)]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getHeistCasinoMainLoot(): ?HeistCasinoMainLoot
    {
        return $this->heistCasinoMainLoot;
    }

    public function setHeistCasinoMainLoot(HeistCasinoMainLoot $heistCasinoMainLoot): static
    {
        $this->heistCasinoMainLoot = $heistCasinoMainLoot;

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

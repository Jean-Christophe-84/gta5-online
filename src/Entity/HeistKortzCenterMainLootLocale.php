<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_main_loot_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class HeistKortzCenterMainLootLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterMainLootLocales')]
    private HeistKortzCenterMainLoot $heistKortzCenterMainLoot;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterMainLootLocales')]
    #[ORM\JoinColumn(nullable: false)]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getHeistKortzCenterMainLoot(): ?HeistKortzCenterMainLoot
    {
        return $this->heistKortzCenterMainLoot;
    }

    public function setHeistKortzCenterMainLoot(HeistKortzCenterMainLoot $heistKortzCenterMainLoot): static
    {
        $this->heistKortzCenterMainLoot = $heistKortzCenterMainLoot;

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

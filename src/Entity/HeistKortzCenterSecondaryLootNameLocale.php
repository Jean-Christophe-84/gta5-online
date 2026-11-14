<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_secondary_loot_name_locale')]
class HeistKortzCenterSecondaryLootNameLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterSecondaryLootNameLocales')]
    private HeistKortzCenterSecondaryLootName $heistKortzCenterSecondaryLootName;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterSecondaryLootNameLocales')]
    #[ORM\JoinColumn(nullable: false)]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getHeistKortzCenterSecondaryLootName(): ?HeistKortzCenterSecondaryLootName
    {
        return $this->heistKortzCenterSecondaryLootName;
    }

    public function setHeistKortzCenterSecondaryLootName(HeistKortzCenterSecondaryLootName $heistKortzCenterSecondaryLootName): static
    {
        $this->heistKortzCenterSecondaryLootName = $heistKortzCenterSecondaryLootName;

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

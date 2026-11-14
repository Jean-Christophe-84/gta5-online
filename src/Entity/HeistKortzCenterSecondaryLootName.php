<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_secondary_loot_name')]
class HeistKortzCenterSecondaryLootName
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
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterSecondaryLootNames')]
    private HeistKortzCenterSecondaryLoot $heistKortzCenterSecondaryLoot;

    #[ORM\OneToMany(targetEntity: HeistKortzCenterHeistKortzCenterSecondaryLoot::class, mappedBy: 'heistKortzCenterSecondaryLootName')]
    public Collection $heistKortzCenterHeistKortzCenterSecondaryLoots {
        get {
            return $this->heistKortzCenterHeistKortzCenterSecondaryLoots;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistKortzCenterSecondaryLootNameLocale::class, mappedBy: 'heistKortzCenterSecondaryLootName')]
    public Collection $heistKortzCenterSecondaryLootNameLocales {
        get {
            return $this->heistKortzCenterSecondaryLootNameLocales;
        }
    }

    public function __construct()
    {
        $this->heistKortzCenterHeistKortzCenterSecondaryLoots = new ArrayCollection();
        $this->heistKortzCenterSecondaryLootNameLocales       = new ArrayCollection();
    }

    public function getHeistKortzCenterSecondaryLoot(): ?HeistKortzCenterSecondaryLoot
    {
        return $this->heistKortzCenterSecondaryLoot;
    }

    public function setHeistKortzCenterSecondaryLoot(HeistKortzCenterSecondaryLoot $heistKortzCenterSecondaryLoot): static
    {
        $this->heistKortzCenterSecondaryLoot = $heistKortzCenterSecondaryLoot;

        return $this;
    }
}

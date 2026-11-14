<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_main_loot')]
class HeistKortzCenterMainLoot
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false)]
    private int $price;

    #[ORM\OneToMany(targetEntity: HeistKortzCenter::class, mappedBy: 'mainLoot')]
    public Collection $heistKortzCenters {
        get {
            return $this->heistKortzCenters;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistKortzCenterMainLootLocale::class, mappedBy: 'heistKortzCenterMainLoot')]
    public Collection $heistKortzCenterMainLootLocales {
        get {
            return $this->heistKortzCenterMainLootLocales;
        }
    }

    public function __construct()
    {
        $this->heistKortzCenterMainLootLocales = new ArrayCollection();
        $this->heistKortzCenters               = new ArrayCollection();
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }
}

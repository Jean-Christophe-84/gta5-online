<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_main_loot')]
class HeistCasinoMainLoot
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
    private ?int $price = null;

    #[ORM\OneToMany(targetEntity: HeistCasino::class, mappedBy: 'mainLoot')]
    public Collection $heistCasinos {
        get {
            return $this->heistCasinos;
        }
    }

    use InfoUpdateTrait;

    #[ORM\OneToMany(targetEntity: HeistCasinoMainLootLocale::class, mappedBy: 'heistCasinoMainLoot')]
    public Collection $heistCasinoMainLootLocales {
        get {
            return $this->heistCasinoMainLootLocales;
        }
    }

    public function __construct()
    {
        $this->heistCasinoMainLootLocales = new ArrayCollection();
        $this->heistCasinos               = new ArrayCollection();
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

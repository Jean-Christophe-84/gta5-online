<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_cayo_perico_main_loot')]
class HeistCayoPericoMainLoot
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

    #[ORM\Column(nullable: false)]
    private ?int $percentageFirstHeistWeek = null;

    #[ORM\Column(nullable: false)]
    private ?int $percentageOtherHeistWeek = null;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $percentageBonusSecondaryLoot = 0;

    #[ORM\OneToMany(targetEntity: HeistCayoPerico::class, mappedBy: 'mainLoot')]
    public Collection $heistCayoPericos {
        get {
            return $this->heistCayoPericos;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistCayoPericoMainLootLocale::class, mappedBy: 'heistCayoPericoMainLoot')]
    public Collection $heistCayoPericoMainLootLocales {
        get {
            return $this->heistCayoPericoMainLootLocales;
        }
    }

    public function __construct()
    {
        $this->heistCayoPericoMainLootLocales = new ArrayCollection();
        $this->heistCayoPericos               = new ArrayCollection();
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

    public function getPercentageFirstHeistWeek(): ?int
    {
        return $this->percentageFirstHeistWeek;
    }

    public function setPercentageFirstHeistWeek(int $percentageFirstHeistWeek): static
    {
        $this->percentageFirstHeistWeek = $percentageFirstHeistWeek;

        return $this;
    }

    public function getPercentageBonusSecondaryLoot(): int
    {
        return $this->percentageBonusSecondaryLoot;
    }

    public function setPercentageBonusSecondaryLoot(int $percentageBonusSecondaryLoot): static
    {
        $this->percentageBonusSecondaryLoot = $percentageBonusSecondaryLoot;

        return $this;
    }

    public function getPercentageOtherHeistWeek(): ?int
    {
        return $this->percentageOtherHeistWeek;
    }

    public function setPercentageOtherHeistWeek(int $percentageOtherHeistWeek): static
    {
        $this->percentageOtherHeistWeek = $percentageOtherHeistWeek;

        return $this;
    }
}

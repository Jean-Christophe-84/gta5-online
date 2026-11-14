<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_cayo_perico_secondary_loot')]
class HeistCayoPericoSecondaryLoot
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(nullable: false)]
    private int $priceMinimum;

    #[ORM\Column(nullable: false)]
    private int $priceMaximum;

    #[ORM\Column(nullable: false)]
    private float $percentageBag;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPriceMinimum(): ?int
    {
        return $this->priceMinimum;
    }

    public function setPriceMinimum(int $priceMinimum): static
    {
        $this->priceMinimum = $priceMinimum;

        return $this;
    }

    public function getPriceMaximum(): ?int
    {
        return $this->priceMaximum;
    }

    public function setPriceMaximum(int $priceMaximum): static
    {
        $this->priceMaximum = $priceMaximum;

        return $this;
    }

    public function getPercentageBag(): ?float
    {
        return $this->percentageBag;
    }

    public function setPercentageBag(float $percentageBag): static
    {
        $this->percentageBag = $percentageBag;

        return $this;
    }
}

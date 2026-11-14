<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'configuration')]
class Configuration
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $percentageVehicles = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $percentageProperties = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $percentageWeapons = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $percentagePromotions = 0;

    use InfoUpdateTrait;

    public function getPercentageVehicles(): ?string
    {
        return $this->percentageVehicles;
    }

    public function setPercentageVehicles(?string $percentageVehicles): static
    {
        $this->percentageVehicles = $percentageVehicles;

        return $this;
    }

    public function getPercentageProperties(): ?string
    {
        return $this->percentageProperties;
    }

    public function setPercentageProperties(?string $percentageProperties): static
    {
        $this->percentageProperties = $percentageProperties;

        return $this;
    }

    public function getPercentageWeapons(): ?string
    {
        return $this->percentageWeapons;
    }

    public function setPercentageWeapons(?string $percentageWeapons): static
    {
        $this->percentageWeapons = $percentageWeapons;

        return $this;
    }

    public function getPercentagePromotions(): ?string
    {
        return $this->percentagePromotions;
    }

    public function setPercentagePromotions(?string $percentagePromotions): static
    {
        $this->percentagePromotions = $percentagePromotions;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\HeistKortzCenterType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_secondary_loot')]
class HeistKortzCenterSecondaryLoot
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
    private float $percentageBag;

    #[ORM\Column(nullable: false, enumType: HeistKortzCenterType::class)]
    private HeistKortzCenterType $type;

    #[ORM\OneToMany(targetEntity: HeistKortzCenterSecondaryLootName::class, mappedBy: 'heistKortzCenterSecondaryLoot')]
    public Collection $heistKortzCenterSecondaryLootNames {
        get {
            return $this->heistKortzCenterSecondaryLootNames;
        }
    }

    public function __construct()
    {
        $this->heistKortzCenterSecondaryLootNames = new ArrayCollection();
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

    public function getType(): HeistKortzCenterType
    {
        return $this->type;
    }

    public function setType(HeistKortzCenterType $type): static
    {
        $this->type = $type;

        return $this;
    }
}

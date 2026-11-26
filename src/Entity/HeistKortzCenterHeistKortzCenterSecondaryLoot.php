<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center_heist_kortz_center_secondary_loot')]
class HeistKortzCenterHeistKortzCenterSecondaryLoot
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterHeistKortzCenterSecondaryLoots')]
    private User $user;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenterHeistKortzCenterSecondaryLoots')]
    private HeistKortzCenterSecondaryLootName $heistKortzCenterSecondaryLootName;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private bool $active = false;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private bool $buyerRequest = false;

    use InfoUpdateTrait;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getHeistKortzCenterSecondaryLootName(): ?HeistKortzCenterSecondaryLootName
    {
        return $this->heistKortzCenterSecondaryLootName;
    }

    public function setHeistKortzCenterSecondaryLootName(HeistKortzCenterSecondaryLootName $heistKortzCenterSecondaryLootName): static
    {
        $this->heistKortzCenterSecondaryLootName = $heistKortzCenterSecondaryLootName;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isBuyerRequest(): bool
    {
        return $this->buyerRequest;
    }

    public function setBuyerRequest(bool $buyerRequest): static
    {
        $this->buyerRequest = $buyerRequest;

        return $this;
    }
}

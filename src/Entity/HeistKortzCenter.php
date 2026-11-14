<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use App\Enum\HeistKortzCenterDifficulty;
use App\Enum\HeistKortzCenterMaterial;
use App\Enum\HeistKortzCenterVehicle;
use App\Enum\HeistKortzCenterWeapon;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_kortz_center')]
class HeistKortzCenter
{
    const string PERMISSION_HEIST_KORTZ_CENTER_VIEW   = 'HEIST_KORTZ_CENTER_VIEW';
    const string PERMISSION_HEIST_KORTZ_CENTER_ADD    = 'HEIST_KORTZ_CENTER_ADD';
    const string PERMISSION_HEIST_KORTZ_CENTER_EDIT   = 'HEIST_KORTZ_CENTER_EDIT';
    const string PERMISSION_HEIST_KORTZ_CENTER_DELETE = 'HEIST_KORTZ_CENTER_DELETE';

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\OneToOne]
    private User $user;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistKortzCenters')]
    private ?HeistKortzCenterMainLoot $mainLoot = null;

    #[ORM\Column(nullable: true, options: ['default' => null])]
    private ?bool $firstTimeInWeek = null;

    #[ORM\Column(nullable: true, enumType: HeistKortzCenterDifficulty::class, options: ['default' => null])]
    private ?HeistKortzCenterDifficulty $difficulty = null;

    #[ORM\Column(nullable: true, enumType: HeistKortzCenterMaterial::class, options: ['default' => null])]
    private ?HeistKortzCenterMaterial $material = null;

    #[ORM\Column(nullable: true, enumType: HeistKortzCenterWeapon::class, options: ['default' => null])]
    private ?HeistKortzCenterWeapon $weapon = null;

    #[ORM\Column(nullable: true, enumType: HeistKortzCenterVehicle::class, options: ['default' => null])]
    private ?HeistKortzCenterVehicle $vehicle = null;

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

    public function getMainLoot(): ?HeistKortzCenterMainLoot
    {
        return $this->mainLoot;
    }

    public function setMainLoot(?HeistKortzCenterMainLoot $mainLoot): static
    {
        $this->mainLoot = $mainLoot;

        return $this;
    }

    public function isFirstTimeInWeek(): ?bool
    {
        return $this->firstTimeInWeek;
    }

    public function setFirstTimeInWeek(?bool $firstTimeInWeek): static
    {
        $this->firstTimeInWeek = $firstTimeInWeek;

        return $this;
    }

    public function getDifficulty(): ?HeistKortzCenterDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(?HeistKortzCenterDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getMaterial(): ?HeistKortzCenterMaterial
    {
        return $this->material;
    }

    public function setMaterial(?HeistKortzCenterMaterial $material): static
    {
        $this->material = $material;

        return $this;
    }

    public function getWeapon(): ?HeistKortzCenterWeapon
    {
        return $this->weapon;
    }

    public function setWeapon(?HeistKortzCenterWeapon $weapon): static
    {
        $this->weapon = $weapon;

        return $this;
    }

    public function getVehicle(): ?HeistKortzCenterVehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?HeistKortzCenterVehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }
}

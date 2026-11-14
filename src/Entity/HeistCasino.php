<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use App\Enum\HeistCasinoApproach;
use App\Enum\HeistCasinoDifficulty;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino')]
class HeistCasino
{
    const string PERMISSION_HEIST_CASINO_VIEW   = 'HEIST_CASINO_VIEW';
    const string PERMISSION_HEIST_CASINO_ADD    = 'HEIST_CASINO_ADD';
    const string PERMISSION_HEIST_CASINO_EDIT   = 'HEIST_CASINO_EDIT';
    const string PERMISSION_HEIST_CASINO_DELETE = 'HEIST_CASINO_DELETE';

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\OneToOne]
    private User $user;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinos')]
    private ?HeistCasinoMainLoot $mainLoot = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinos')]
    private ?HeistCasinoHacker $hacker = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinos')]
    private ?HeistCasinoDriver $driver = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistCasinos')]
    private ?HeistCasinoGunman $gunman = null;

    #[ORM\Column(nullable: true, options: ['default' => null])]
    private ?bool $firstTimeInWeek = null;

    #[ORM\Column(nullable: true, enumType: HeistCasinoDifficulty::class, options: ['default' => null])]
    private ?HeistCasinoDifficulty $difficulty = null;

    #[ORM\Column(nullable: true, enumType: HeistCasinoApproach::class, options: ['default' => null])]
    private ?HeistCasinoApproach $approach = null;

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

    public function getMainLoot(): ?HeistCasinoMainLoot
    {
        return $this->mainLoot;
    }

    public function setMainLoot(?HeistCasinoMainLoot $mainLoot): static
    {
        $this->mainLoot = $mainLoot;

        return $this;
    }

    public function getHacker(): ?HeistCasinoHacker
    {
        return $this->hacker;
    }

    public function setHacker(?HeistCasinoHacker $hacker): static
    {
        $this->hacker = $hacker;

        return $this;
    }

    public function getDriver(): ?HeistCasinoDriver
    {
        return $this->driver;
    }

    public function setDriver(?HeistCasinoDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getGunman(): ?HeistCasinoGunman
    {
        return $this->gunman;
    }

    public function setGunman(?HeistCasinoGunman $gunman): static
    {
        $this->gunman = $gunman;

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

    public function getDifficulty(): ?HeistCasinoDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(?HeistCasinoDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getApproach(): ?HeistCasinoApproach
    {
        return $this->approach;
    }

    public function setApproach(?HeistCasinoApproach $approach): static
    {
        $this->approach = $approach;

        return $this;
    }
}

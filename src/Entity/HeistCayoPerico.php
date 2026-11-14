<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use App\Enum\HeistCayoPericoDifficulty;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_cayo_perico')]
class HeistCayoPerico
{
    const string PERMISSION_HEIST_CAYO_PERICO_VIEW   = 'HEIST_CAYO_PERICO_VIEW';
    const string PERMISSION_HEIST_CAYO_PERICO_ADD    = 'HEIST_CAYO_PERICO_ADD';
    const string PERMISSION_HEIST_CAYO_PERICO_EDIT   = 'HEIST_CAYO_PERICO_EDIT';
    const string PERMISSION_HEIST_CAYO_PERICO_DELETE = 'HEIST_CAYO_PERICO_DELETE';

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\OneToOne]
    private User $user;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'heistCayoPericos')]
    private ?HeistCayoPericoMainLoot $mainLoot = null;

    #[ORM\Column(nullable: true, enumType: HeistCayoPericoDifficulty::class, options: ['default' => null])]
    private ?HeistCayoPericoDifficulty $difficulty = null;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineZoneOne = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedZoneOne = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashZoneOne = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineZoneThree = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedZoneThree = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashZoneThree = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineZoneSix = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedZoneSix = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashZoneSix = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $goldMainBasement = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineMainBasement = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedMainBasement = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $artworkMainBasement = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashMainBasement = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $artworkMainDesktop = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $goldMainWest = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineMainWest = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedMainWest = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $artworkMainWest = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashMainWest = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $goldMainNorth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineMainNorth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedMainNorth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $artworkMainNorth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashMainNorth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $goldMainSouth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cocaineMainSouth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $weedMainSouth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $artworkMainSouth = 0;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $cashMainSouth = 0;

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

    public function getMainLoot(): ?HeistCayoPericoMainLoot
    {
        return $this->mainLoot;
    }

    public function setMainLoot(?HeistCayoPericoMainLoot $mainLoot): static
    {
        $this->mainLoot = $mainLoot;

        return $this;
    }

    public function getDifficulty(): ?HeistCayoPericoDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(?HeistCayoPericoDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getCocaineZoneOne(): ?int
    {
        return $this->cocaineZoneOne;
    }

    public function setCocaineZoneOne(int $cocaineZoneOne): static
    {
        $this->cocaineZoneOne = $cocaineZoneOne;

        return $this;
    }

    public function getWeedZoneOne(): ?int
    {
        return $this->weedZoneOne;
    }

    public function setWeedZoneOne(int $weedZoneOne): static
    {
        $this->weedZoneOne = $weedZoneOne;

        return $this;
    }

    public function getCashZoneOne(): ?int
    {
        return $this->cashZoneOne;
    }

    public function setCashZoneOne(int $cashZoneOne): static
    {
        $this->cashZoneOne = $cashZoneOne;

        return $this;
    }

    public function getCocaineZoneThree(): ?int
    {
        return $this->cocaineZoneThree;
    }

    public function setCocaineZoneThree(int $cocaineZoneThree): static
    {
        $this->cocaineZoneThree = $cocaineZoneThree;

        return $this;
    }

    public function getWeedZoneThree(): ?int
    {
        return $this->weedZoneThree;
    }

    public function setWeedZoneThree(int $weedZoneThree): static
    {
        $this->weedZoneThree = $weedZoneThree;

        return $this;
    }

    public function getCashZoneThree(): ?int
    {
        return $this->cashZoneThree;
    }

    public function setCashZoneThree(int $cashZoneThree): static
    {
        $this->cashZoneThree = $cashZoneThree;

        return $this;
    }

    public function getCocaineZoneSix(): ?int
    {
        return $this->cocaineZoneSix;
    }

    public function setCocaineZoneSix(int $cocaineZoneSix): static
    {
        $this->cocaineZoneSix = $cocaineZoneSix;

        return $this;
    }

    public function getWeedZoneSix(): ?int
    {
        return $this->weedZoneSix;
    }

    public function setWeedZoneSix(int $weedZoneSix): static
    {
        $this->weedZoneSix = $weedZoneSix;

        return $this;
    }

    public function getCashZoneSix(): ?int
    {
        return $this->cashZoneSix;
    }

    public function setCashZoneSix(int $cashZoneSix): static
    {
        $this->cashZoneSix = $cashZoneSix;

        return $this;
    }

    public function getGoldMainBasement(): ?int
    {
        return $this->goldMainBasement;
    }

    public function setGoldMainBasement(int $goldMainBasement): static
    {
        $this->goldMainBasement = $goldMainBasement;

        return $this;
    }

    public function getCocaineMainBasement(): ?int
    {
        return $this->cocaineMainBasement;
    }

    public function setCocaineMainBasement(int $cocaineMainBasement): static
    {
        $this->cocaineMainBasement = $cocaineMainBasement;

        return $this;
    }

    public function getWeedMainBasement(): ?int
    {
        return $this->weedMainBasement;
    }

    public function setWeedMainBasement(int $weedMainBasement): static
    {
        $this->weedMainBasement = $weedMainBasement;

        return $this;
    }

    public function getArtworkMainBasement(): ?int
    {
        return $this->artworkMainBasement;
    }

    public function setArtworkMainBasement(int $artworkMainBasement): static
    {
        $this->artworkMainBasement = $artworkMainBasement;

        return $this;
    }

    public function getCashMainBasement(): ?int
    {
        return $this->cashMainBasement;
    }

    public function setCashMainBasement(int $cashMainBasement): static
    {
        $this->cashMainBasement = $cashMainBasement;

        return $this;
    }

    public function getArtworkMainDesktop(): ?int
    {
        return $this->artworkMainDesktop;
    }

    public function setArtworkMainDesktop(int $artworkMainDesktop): static
    {
        $this->artworkMainDesktop = $artworkMainDesktop;

        return $this;
    }

    public function getGoldMainWest(): ?int
    {
        return $this->goldMainWest;
    }

    public function setGoldMainWest(int $goldMainWest): static
    {
        $this->goldMainWest = $goldMainWest;

        return $this;
    }

    public function getCocaineMainWest(): ?int
    {
        return $this->cocaineMainWest;
    }

    public function setCocaineMainWest(int $cocaineMainWest): static
    {
        $this->cocaineMainWest = $cocaineMainWest;

        return $this;
    }

    public function getWeedMainWest(): ?int
    {
        return $this->weedMainWest;
    }

    public function setWeedMainWest(int $weedMainWest): static
    {
        $this->weedMainWest = $weedMainWest;

        return $this;
    }

    public function getArtworkMainWest(): ?int
    {
        return $this->artworkMainWest;
    }

    public function setArtworkMainWest(int $artworkMainWest): static
    {
        $this->artworkMainWest = $artworkMainWest;

        return $this;
    }

    public function getCashMainWest(): ?int
    {
        return $this->cashMainWest;
    }

    public function setCashMainWest(int $cashMainWest): static
    {
        $this->cashMainWest = $cashMainWest;

        return $this;
    }

    public function getGoldMainNorth(): ?int
    {
        return $this->goldMainNorth;
    }

    public function setGoldMainNorth(int $goldMainNorth): static
    {
        $this->goldMainNorth = $goldMainNorth;

        return $this;
    }

    public function getCocaineMainNorth(): ?int
    {
        return $this->cocaineMainNorth;
    }

    public function setCocaineMainNorth(int $cocaineMainNorth): static
    {
        $this->cocaineMainNorth = $cocaineMainNorth;

        return $this;
    }

    public function getWeedMainNorth(): ?int
    {
        return $this->weedMainNorth;
    }

    public function setWeedMainNorth(int $weedMainNorth): static
    {
        $this->weedMainNorth = $weedMainNorth;

        return $this;
    }

    public function getArtworkMainNorth(): ?int
    {
        return $this->artworkMainNorth;
    }

    public function setArtworkMainNorth(int $artworkMainNorth): static
    {
        $this->artworkMainNorth = $artworkMainNorth;

        return $this;
    }

    public function getCashMainNorth(): ?int
    {
        return $this->cashMainNorth;
    }

    public function setCashMainNorth(int $cashMainNorth): static
    {
        $this->cashMainNorth = $cashMainNorth;

        return $this;
    }

    public function getGoldMainSouth(): ?int
    {
        return $this->goldMainSouth;
    }

    public function setGoldMainSouth(int $goldMainSouth): static
    {
        $this->goldMainSouth = $goldMainSouth;

        return $this;
    }

    public function getCocaineMainSouth(): ?int
    {
        return $this->cocaineMainSouth;
    }

    public function setCocaineMainSouth(int $cocaineMainSouth): static
    {
        $this->cocaineMainSouth = $cocaineMainSouth;

        return $this;
    }

    public function getWeedMainSouth(): ?int
    {
        return $this->weedMainSouth;
    }

    public function setWeedMainSouth(int $weedMainSouth): static
    {
        $this->weedMainSouth = $weedMainSouth;

        return $this;
    }

    public function getArtworkMainSouth(): ?int
    {
        return $this->artworkMainSouth;
    }

    public function setArtworkMainSouth(int $artworkMainSouth): static
    {
        $this->artworkMainSouth = $artworkMainSouth;

        return $this;
    }

    public function getCashMainSouth(): ?int
    {
        return $this->cashMainSouth;
    }

    public function setCashMainSouth(int $cashMainSouth): static
    {
        $this->cashMainSouth = $cashMainSouth;

        return $this;
    }
}

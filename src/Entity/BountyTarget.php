<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bounty_target')]
class BountyTarget
{
    const string PERMISSION_BOUNTY_TARGET_VIEW   = 'BOUNTY_TARGET_VIEW';
    const string PERMISSION_BOUNTY_TARGET_ADD    = 'BOUNTY_TARGET_ADD';
    const string PERMISSION_BOUNTY_TARGET_EDIT   = 'BOUNTY_TARGET_EDIT';
    const string PERMISSION_BOUNTY_TARGET_DONE   = 'BOUNTY_TARGET_DONE';
    const string PERMISSION_BOUNTY_TARGET_DELETE = 'BOUNTY_TARGET_DELETE';

    const int PRICE_MIN             = 50000;
    const int PRICE_MAX             = 60000;
    const int PRICE_MOST_WANTED_MIN = 180000;
    const int PRICE_MOST_WANTED_MAX = 200000;
    const int PRICE_DEAD_DIVISOR    = 4;

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(inversedBy: 'bountyTargets')]
    private ?BountyMission $bountyMission = null;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $mostWanted = false;

    #[ORM\OneToMany(targetEntity: BountyTargetUser::class, mappedBy: 'bountyTarget')]
    public Collection $bountyTargetUsers {
        get {
            return $this->bountyTargetUsers;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->bountyTargetUsers = new ArrayCollection();
    }

    public function getBountyMission(): ?BountyMission
    {
        return $this->bountyMission;
    }

    public function setBountyMission(?BountyMission $bountyMission): static
    {
        $this->bountyMission = $bountyMission;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isMostWanted(): ?bool
    {
        return $this->mostWanted;
    }

    public function setMostWanted(bool $mostWanted): static
    {
        $this->mostWanted = $mostWanted;

        return $this;
    }
}

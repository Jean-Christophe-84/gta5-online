<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bounty_mission')]
class BountyMission
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: BountyMissionLocale::class, mappedBy: 'bountyMission')]
    public Collection $bountyMissionLocales {
        get {
            return $this->bountyMissionLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: BountyTarget::class, mappedBy: 'bountyMission')]
    public Collection $bountyTargets {
        get {
            return $this->bountyTargets;
        }
    }

    public function __construct()
    {
        $this->bountyMissionLocales = new ArrayCollection();
        $this->bountyTargets        = new ArrayCollection();
    }
}

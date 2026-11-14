<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'bounty_mission_locale')]
#[UniqueEntity(fields: ['locale', 'name'])]
class BountyMissionLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'bountyMissionLocales')]
    private BountyMission $bountyMission;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'bountyMissionLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $name;

    public function getBountyMission(): ?BountyMission
    {
        return $this->bountyMission;
    }

    public function setBountyMission(BountyMission $bountyMission): static
    {
        $this->bountyMission = $bountyMission;

        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): static
    {
        $this->locale = $locale;

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
}

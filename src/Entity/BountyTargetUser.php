<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bounty_target_user')]
class BountyTargetUser
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'bountyTargetUsers')]
    // @note: nullable=false by default
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'bountyTargetUsers')]
    // @note: nullable=false by default
    private BountyTarget $bountyTarget;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $done = false;

    use InfoUpdateTrait;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBountyTarget(): ?BountyTarget
    {
        return $this->bountyTarget;
    }

    public function setBountyTarget(?BountyTarget $bountyTarget): static
    {
        $this->bountyTarget = $bountyTarget;

        return $this;
    }

    public function isDone(): ?bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;

        return $this;
    }
}

<?php

namespace App\Entity\Traits;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
trait InfoUpdateTrait
{
    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $updatedBy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTime $updatedAt = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(): static
    {
        $this->createdAt = new DateTime();

        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): static
    {
        $this->updatedAt = new DateTime();

        return $this;
    }
}

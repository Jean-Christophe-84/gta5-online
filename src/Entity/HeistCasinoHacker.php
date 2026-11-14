<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'heist_casino_hacker')]
class HeistCasinoHacker
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(nullable: false)]
    private ?int $percent = null;

    #[ORM\Column(nullable: false)]
    private ?int $timeIfNotDetected = null;

    #[ORM\Column(nullable: false)]
    private ?int $timeIfDetected = null;

    #[ORM\OneToMany(targetEntity: HeistCasino::class, mappedBy: 'hacker')]
    public Collection $heistCasinos {
        get {
            return $this->heistCasinos;
        }
    }

    public function __construct()
    {
        $this->heistCasinos = new ArrayCollection();
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

    public function getPercent(): ?int
    {
        return $this->percent;
    }

    public function setPercent(int $percent): static
    {
        $this->percent = $percent;

        return $this;
    }

    public function getTimeIfNotDetected(): ?int
    {
        return $this->timeIfNotDetected;
    }

    public function setTimeIfNotDetected(int $timeIfNotDetected): static
    {
        $this->timeIfNotDetected = $timeIfNotDetected;

        return $this;
    }

    public function getTimeIfDetected(): ?int
    {
        return $this->timeIfDetected;
    }

    public function setTimeIfDetected(int $timeIfDetected): static
    {
        $this->timeIfDetected = $timeIfDetected;

        return $this;
    }
}

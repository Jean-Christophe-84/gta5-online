<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_statistic')]
class UserStatistic
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private string $year;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $month = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $day = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $hour = null;

    #[ORM\Column(length: 256, nullable: false)]
    private int $numberUsers;

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function setYear(string $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(?string $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(?string $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getHour(): ?string
    {
        return $this->hour;
    }

    public function setHour(?string $hour): self
    {
        $this->hour = $hour;

        return $this;
    }

    public function getNumberUsers(): ?int
    {
        return $this->numberUsers;
    }

    public function setNumberUsers(int $numberUsers): self
    {
        $this->numberUsers = $numberUsers;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'title_update')]
class TitleUpdate
{
    const string PERMISSION_TITLE_UPDATE_VIEW   = 'TITLE_UPDATE_VIEW';
    const string PERMISSION_TITLE_UPDATE_ADD    = 'TITLE_UPDATE_ADD';
    const string PERMISSION_TITLE_UPDATE_EDIT   = 'TITLE_UPDATE_EDIT';
    const string PERMISSION_TITLE_UPDATE_DELETE = 'TITLE_UPDATE_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(nullable: false)]
    private DateTime $releaseDate;

    #[ORM\OneToMany(targetEntity: Business::class, mappedBy: 'titleUpdate')]
    public Collection $businesses {
        get {
            return $this->businesses;
        }
    }

    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'titleUpdate')]
    public Collection $properties {
        get {
            return $this->properties;
        }
    }

    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'titleUpdate')]
    public Collection $vehicles {
        get {
            return $this->vehicles;
        }
    }

    #[ORM\OneToMany(targetEntity: Weapon::class, mappedBy: 'titleUpdate')]
    public Collection $weapons {
        get {
            return $this->weapons;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->businesses = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->vehicles   = new ArrayCollection();
        $this->weapons    = new ArrayCollection();
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

    public function getReleaseDate(): ?DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(DateTime $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }
}

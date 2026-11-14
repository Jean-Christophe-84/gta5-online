<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'group_gta')]
class GroupGta
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(name: '`default`', nullable: false, options: ['default' => false])]
    private bool $default = false;

    #[ORM\OneToMany(targetEntity: GroupGtaLocale::class, mappedBy: 'groupGta')]
    public Collection $groupGtaLocales {
        get {
            return $this->groupGtaLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: PermissionGroupGta::class, mappedBy: 'groupGta')]
    public Collection $permissionGroups {
        get {
            return $this->permissionGroups;
        }
    }

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'groupGta')]
    public Collection $users {
        get {
            return $this->users;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->groupGtaLocales  = new ArrayCollection();
        $this->permissionGroups = new ArrayCollection();
        $this->users            = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getGroupGtaLocales(): Collection
    {
        return $this->groupGtaLocales;
    }

    public function getPermissionGroups(): Collection
    {
        return $this->permissionGroups;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }
}

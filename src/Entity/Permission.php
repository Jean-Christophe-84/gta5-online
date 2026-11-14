<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'permission')]
class Permission
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
    private string $bundle;

    #[ORM\Column(length: 256, nullable: false)]
    private string $attribute;

    #[ORM\OneToMany(targetEntity: PermissionGroupGta::class, mappedBy: 'permission')]
    private Collection $permissionGroups {
        get {
            return $this->permissionGroups;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->permissionGroups = new ArrayCollection();
    }

    public function getBundle(): string
    {
        return $this->bundle;
    }

    public function setBundle(string $bundle): static
    {
        $this->bundle = $bundle;

        return $this;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function setAttribute(string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}

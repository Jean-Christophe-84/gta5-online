<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'permission_group_gta')]
class PermissionGroupGta
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'permissionGroups')]
    private GroupGta $groupGta;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'permissionGroups')]
    private Permission $permission;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $active = false;

    use InfoUpdateTrait;

    public function getGroupGta(): ?GroupGta
    {
        return $this->groupGta;
    }

    public function setGroupGta(GroupGta $groupGta): self
    {
        $this->groupGta = $groupGta;

        return $this;
    }

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public function setPermission(Permission $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}

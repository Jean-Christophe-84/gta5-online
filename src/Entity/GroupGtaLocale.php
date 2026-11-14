<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'group_gta_locale')]
#[UniqueEntity(fields: ['name'], message: 'this_name_already_exists')]
class GroupGtaLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'groupGtaLocales')]
    private GroupGta $groupGta;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'groupGtaLocales')]
    private Locale $locale;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $description;

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

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}

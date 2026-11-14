<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customization')]
class Customization
{
    const string PERMISSION_CUSTOMIZATION_VIEW   = 'CUSTOMIZATION_VIEW';
    const string PERMISSION_CUSTOMIZATION_ADD    = 'CUSTOMIZATION_ADD';
    const string PERMISSION_CUSTOMIZATION_EDIT   = 'CUSTOMIZATION_EDIT';
    const string PERMISSION_CUSTOMIZATION_DELETE = 'CUSTOMIZATION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: CustomizationLocale::class, mappedBy: 'customization')]
    public Collection $customizationLocales {
        get {
            return $this->customizationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponCustomization::class, mappedBy: 'customization')]
    public Collection $weaponCustomizations {
        get {
            return $this->weaponCustomizations;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->customizationLocales = new ArrayCollection();
        $this->weaponCustomizations = new ArrayCollection();
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'modification_location')]
class ModificationLocation
{
    const string PERMISSION_MODIFICATION_LOCATION_VIEW   = 'MODIFICATION_LOCATION_VIEW';
    const string PERMISSION_MODIFICATION_LOCATION_ADD    = 'MODIFICATION_LOCATION_ADD';
    const string PERMISSION_MODIFICATION_LOCATION_EDIT   = 'MODIFICATION_LOCATION_EDIT';
    const string PERMISSION_MODIFICATION_LOCATION_DELETE = 'MODIFICATION_LOCATION_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: ModificationLocationLocale::class, mappedBy: 'modificationLocation')]
    public Collection $modificationLocationLocales {
        get {
            return $this->modificationLocationLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleModificationLocation::class, mappedBy: 'modificationLocation')]
    public Collection $vehicleModificationLocations {
        get {
            return $this->vehicleModificationLocations;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->modificationLocationLocales  = new ArrayCollection();
        $this->vehicleModificationLocations = new ArrayCollection();
    }
}

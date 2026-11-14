<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_method')]
class DeliveryMethod
{
    const string PERMISSION_DELIVERY_METHOD_VIEW   = 'DELIVERY_METHOD_VIEW';
    const string PERMISSION_DELIVERY_METHOD_ADD    = 'DELIVERY_METHOD_ADD';
    const string PERMISSION_DELIVERY_METHOD_EDIT   = 'DELIVERY_METHOD_EDIT';
    const string PERMISSION_DELIVERY_METHOD_DELETE = 'DELIVERY_METHOD_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\OneToMany(targetEntity: DeliveryMethodLocale::class, mappedBy: 'deliveryMethod')]
    public Collection $deliveryMethodLocales {
        get {
            return $this->deliveryMethodLocales;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleDeliveryMethod::class, mappedBy: 'deliveryMethod')]
    public Collection $vehicleDeliveryMethods {
        get {
            return $this->vehicleDeliveryMethods;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->deliveryMethodLocales   = new ArrayCollection();
        $this->vehicleDeliveryMethods  = new ArrayCollection();
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_locale')]
class VehicleLocale
{
    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleLocales')]
    private Vehicle $vehicle;

    #[ORM\Id]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'vehicleLocales')]
    private Locale $locale;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $unlockVehicle = null;


    use InfoUpdateTrait;

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getUnlockVehicle(): ?string
    {
        return $this->unlockVehicle;
    }

    public function setUnlockVehicle(?string $unlockVehicle): static
    {
        $this->unlockVehicle = $unlockVehicle;

        return $this;
    }
}

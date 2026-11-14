<?php

declare(strict_types=1);

namespace App\Twig;

use App\Helper\VehicleHelper;
use Exception;
use Twig\Attribute\AsTwigFilter;

class VehicleExtension
{
    public function __construct(private readonly VehicleHelper $vehicleHelper)
    {
    }

    #[AsTwigFilter(name: 'evolutionLabelVehicle')]
    public function getEvolutionLabelVehicle(int $evolution): ?string
    {
        return $this->vehicleHelper->getEvolutionLabelVehicle($evolution);
    }

    /**
     * @throws Exception
     */
    #[AsTwigFilter(name: 'nameAndAverageVehicle')]
    public function getNameAndAverageVehicle(int $vehicleId): string
    {
        return $this->vehicleHelper->getNameAndAverageVehicle($vehicleId);
    }

    #[AsTwigFilter(name: 'vehicleTwoLabelVehicle')]
    public function getVehicleTwoLabelVehicle(int $evolution): ?string
    {
        return $this->vehicleHelper->getVehicleTwoLabelVehicle($evolution);
    }
}

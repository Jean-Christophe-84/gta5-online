<?php

declare(strict_types=1);

namespace App\Twig;

use App\Helper\WeaponHelper;
use Exception;
use Twig\Attribute\AsTwigFilter;

class WeaponExtension
{
    public function __construct(private readonly WeaponHelper $weaponHelper)
    {
    }

    #[AsTwigFilter(name: 'evolutionLabelWeapon')]
    public function getEvolutionLabelWeapon(int $evolution): ?string
    {
        return $this->weaponHelper->getEvolutionLabelWeapon($evolution);
    }

    /**
     * @throws Exception
     */
    #[AsTwigFilter(name: 'nameAndAverageWeapon')]
    public function getNameAndAverageWeapon(int $weaponId): string
    {
        return $this->weaponHelper->getNameAndAverageWeapon($weaponId);
    }

    #[AsTwigFilter(name: 'weaponTwoLabelWeapon')]
    public function getWeaponTwoLabelWeapon(int $evolution): ?string
    {
        return $this->weaponHelper->getWeaponTwoLabelWeapon($evolution);
    }
}

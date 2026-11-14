<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistKortzCenterVehicle: string implements TranslatableInterface
{
    case MANCHEZ             = 'manchez';
    case CARACARA_ARMORED    = 'caracara-armored';
    case ANNIHILATOR_STEALTH = 'annihilator-stealth';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_kortz_center_vehicle', ['case' => $this->name], null, $locale);
    }
}

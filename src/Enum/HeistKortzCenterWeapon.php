<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistKortzCenterWeapon: string implements TranslatableInterface
{
    case STREET_ARSENAL   = 'street-arsenal';
    case SECURITY_ARSENAL = 'security-arsenal';
    case MILITARY_ARSENAL = 'military-arsenal';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_kortz_center_weapon', ['case' => $this->name], null, $locale);
    }
}

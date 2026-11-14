<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistKortzCenterMaterial: string implements TranslatableInterface
{
    case EMPLOYEE_ACCESS_CARD = 'employee-access-card';
    case TACTICAL_EQUIPMENT   = 'tactical-equipment';
    case ALPHA_MAIL_OUTFIT    = 'alpha-mail-outfit';
    case SAFETY_SUIT          = 'safety-suit';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_kortz_center_material', ['case' => $this->name], null, $locale);
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SpeedUnit: string implements TranslatableInterface
{
    case KILOMETERS = 'kilometers';
    case MILES      = 'miles';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_speed_unit', ['case' => $this->name], null, $locale);
    }
}

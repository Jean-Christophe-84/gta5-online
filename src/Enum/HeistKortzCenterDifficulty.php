<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistKortzCenterDifficulty: string implements TranslatableInterface
{
    case NORMAL = 'normal';
    case HARD   = 'hard';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_kortz_center_difficulty', ['case' => $this->name], null, $locale);
    }
}

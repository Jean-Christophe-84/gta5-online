<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistCasinoApproach: string implements TranslatableInterface
{
    case CUNNING  = 'cunning';
    case STEALTHY = 'stealthy';
    case BRUTAL   = 'brutal';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_casino_approach', ['case' => $this->name], null, $locale);
    }
}

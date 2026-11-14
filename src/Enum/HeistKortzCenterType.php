<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum HeistKortzCenterType: string implements TranslatableInterface
{
    case VAULT_PAINTING         = 'vault-painting';
    case OTHER_PAINTING         = 'other-painting';
    case DISPLAY_CASE           = 'display-case';
    case LASER_CUT_DISPLAY_CASE = 'laser-cut-display-case';
    case CRATE_CARGO_ZONE       = 'crate-cargo-zone';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum_heist_kortz_center_type', ['case' => $this->name], null, $locale);
    }
}

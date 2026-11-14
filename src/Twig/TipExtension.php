<?php

namespace App\Twig;

use App\Entity\BountyTarget;
use Twig\Attribute\AsTwigFunction;

class TipExtension
{
    // @note: translation key => ICU parameters, shown on the page and on its homepage tile
    private const array TIPS = [
        'bounty_targets' => ['tip_reward_dead' => ['divisor' => BountyTarget::PRICE_DEAD_DIVISOR]],
        'business_goods' => ['tip_dealer_stock_order' => [], 'tip_dealer_double_price' => [], 'tip_production_online_only' => []],
        'cayo_perico'    => ['tip_loot_cutting' => [], 'tip_minimal_preparation' => [], 'tip_phantom_wedge_longfin' => []],
        'kortz_center'   => ['tip_glass_cutter_required' => [], 'tip_itineraries_show_guards' => []],
        'properties'     => ['tip_prefer_largest_garages' => []],
        'vehicles'       => ['tip_one_sale_per_day' => []]
    ];

    #[AsTwigFunction(name: 'tips')]
    public function getTips(string $page): array
    {
        return self::TIPS[$page] ?? [];
    }
}

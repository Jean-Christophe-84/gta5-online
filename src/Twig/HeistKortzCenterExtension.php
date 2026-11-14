<?php

namespace App\Twig;

use App\Enum\HeistKortzCenterType;
use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Twig\Attribute\AsTwigFilter;

class HeistKortzCenterExtension
{
    public function __construct(private readonly ConfigurationHelper $configurationHelper)
    {
    }

    /**
     * @throws DoctrineDBALException
     */
    #[AsTwigFilter(name: 'heistKortzCenterTypeLabel')]
    public function heistKortzCenterTypeLabel(string $heistKortzCenterSecondaryLootType): string
    {
        return $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale(HeistKortzCenterType::from($heistKortzCenterSecondaryLootType))
        );
    }
}

<?php

namespace App\Twig;

use App\Entity\Locale;
use App\Helper\ConfigurationHelper;
use Collator;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ConfigurationExtension
{
    public function __construct(
        private readonly ConfigurationHelper $configurationHelper, private readonly EntityManagerInterface $entityManager,
        private readonly Environment         $environment, private readonly ParameterBagInterface $parameterBag
    ) {
    }

    #[AsTwigFilter(name: 'colon')]
    public function colon(string $text): string
    {
        return $this->configurationHelper->getTextWithColon($text);
    }

    #[AsTwigFilter(name: 'formatMoney')]
    public function formatMoney(float|int|string|null $number, int $decimals = 0): string
    {
        return $this->configurationHelper->formatMoney($number, $decimals);
    }

    #[AsTwigFilter(name: 'formatNumber')]
    public function formatNumber(float|int|string|null $number, int $decimals = 0): string
    {
        return $this->configurationHelper->formatNumber($number, $decimals);
    }

    #[AsTwigFilter(name: 'formatPercent')]
    public function formatPercent(float|int|string|null $number, int $decimals = 0, bool $trimZeros = false): string
    {
        return $this->configurationHelper->formatPercent($number, $decimals, $trimZeros);
    }

    #[AsTwigFilter(name: 'formatSpeed')]
    public function formatSpeed(float|string|null $kilometers, float|string|null $miles): string
    {
        return $this->configurationHelper->formatSpeed($kilometers, $miles);
    }

    #[AsTwigFilter(name: 'mbUcfirst')]
    public function mbUcfirst(string $string): ?string
    {
        return $this->configurationHelper->mbUcfirst($string);
    }

    /**
     * Trie les tuiles de l'accueil par libellé traduit, selon la langue courante (accents compris)
     *
     * @param array<array{html: string, label: string}> $tiles
     * @return array<array{html: string, label: string}>
     */
    #[AsTwigFilter(name: 'sortTiles')]
    public function sortTiles(array $tiles): array
    {
        $collator = new Collator($this->configurationHelper->getDefaultLocale());
        usort($tiles, static fn (array $a, array $b): int => $collator->compare($a['label'], $b['label']));

        return $tiles;
    }

    /**
     * @throws DoctrineDBALException
     */
    #[AsTwigFilter(name: 'translateLocale')]
    public function translateLocale(string|TranslatableInterface $string, array $parameters = [], ?string $locale = null): string
    {
        return $this->configurationHelper->getTranslateLocale($string, $parameters, $locale);
    }

    #[AsTwigFunction(name: 'default_locale')]
    public function default_locale(): string
    {
        return $this->configurationHelper->getDefaultLocale();
    }

    /**
     * @return Locale[]
     */
    #[AsTwigFunction(name: 'locales')]
    public function locales(): array
    {
        return $this->entityManager->getRepository(Locale::class)->findBy([], ['name' => 'ASC']);
    }

    #[AsTwigFunction(name: 'turnstile_html', isSafe: ['html'])]
    public function turnstile_html(array $parameters = []): string
    {
        $html = '';

        foreach ($parameters as $key => $value) {
            $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$value) . '"';
        }

        return '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars((string)$this->parameterBag->get('turnstile_site_key')) . '"' . $html . '></div>';
    }

    /**
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    #[AsTwigFunction(name: 'turnstile_js', isSafe: ['html'])]
    public function turnstile_js(): ?string
    {
        return $this->environment->render('Extension/turnstile_js.html.twig');
    }
}

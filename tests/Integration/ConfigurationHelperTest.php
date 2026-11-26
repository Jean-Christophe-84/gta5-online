<?php

namespace App\Tests\Integration;

use App\Helper\ConfigurationHelper;
use App\Twig\ConfigurationExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ConfigurationHelperTest extends KernelTestCase
{
    public static function formatProvider(): iterable
    {
        yield 'nombre fr' => ['fr', 'formatNumber', [1234567.891, 2], '1 234 567,89'];
        yield 'nombre en' => ['en', 'formatNumber', [1234567.891, 2], '1,234,567.89'];
        yield 'nombre arrondi au supérieur' => ['fr', 'formatNumber', [2.5], '3'];
        yield 'nombre vide' => ['fr', 'formatNumber', [null], '0'];
        yield 'nombre en texte' => ['fr', 'formatNumber', ['1500'], '1 500'];
        yield 'pourcentage fr' => ['fr', 'formatPercent', [12.5, 1], '12,5 %'];
        yield 'pourcentage en' => ['en', 'formatPercent', [12.5, 1], '12.5%'];
        yield 'pourcentage sans zéros inutiles' => ['fr', 'formatPercent', [12, 2, true], '12 %'];
        yield 'montant fr' => ['fr', 'formatMoney', [2550000], '2 550 000$'];
        yield 'vitesse fr en kilomètres' => ['fr', 'formatSpeed', [201.5, 125.2], '201,50 '];
        yield 'vitesse en en miles' => ['en', 'formatSpeed', [201.5, 125.2], '125.20 '];
        yield 'vitesse inconnue' => ['fr', 'formatSpeed', [null, null], ''];
        yield 'deux-points fr' => ['fr', 'getTextWithColon', ['prix'], 'prix :'];
        yield 'majuscule accentuée' => ['fr', 'mbUcfirst', ['élan'], 'Élan'];
    }

    #[DataProvider('formatProvider')]
    public function testFormat(string $locale, string $method, array $arguments, string $expected): void
    {
        $result = $this->getHelper($locale)->$method(...$arguments);

        if ($expected === '') {
            $this->assertSame('', $result);

            return;
        }

        $this->assertStringStartsWith($expected, $this->normalizeSpaces($result));
    }

    public function testMissingFieldIgnoresSpaces(): void
    {
        $helper = $this->getHelper('fr');

        $this->assertNotNull($helper->getMissingFieldMessage(new Request([], ['name' => '   ']), ['name' => 'please_enter_the_name']));
        $this->assertNotNull($helper->getMissingFieldMessage(new Request([], []), ['name' => 'please_enter_the_name']));
        $this->assertNull($helper->getMissingFieldMessage(new Request([], ['name' => '0']), ['name' => 'please_enter_the_name']));
    }

    public function testOrderByOnlyKeepsSafeColumnsAndDirections(): void
    {
        $helper = $this->getHelper('fr');
        $query  = 'SELECT vehicle.id, vehicle_alias.label FROM vehicle LEFT JOIN vehicle vehicle_alias ON vehicle_alias.id = vehicle.id';

        $this->assertSame(
            ' ORDER BY vehicle.name ASC, COALESCE(vehicle.price, 0) DESC, vehicle_alias.label ASC',
            $helper->getOrderBy(
                $query,
                [
                    'vehicle-name'                  => 'ASC',
                    'vehicle-price'                 => 'DESC',
                    'vehicle_alias-label'           => 'ASC',
                    'vehicle-name; DROP TABLE user' => 'ASC',
                    'vehicle-speed'                 => 'ASC; DROP TABLE user',
                    'vehicle.speed'                 => 'ASC',
                    'vehicle-unknown_column'        => 'ASC',
                    'user-id'                       => 'ASC',
                    'unknown_table-name'            => 'ASC',
                    0                               => 'ASC'
                ],
                ['vehicle-price' => 'COALESCE(vehicle.price, 0)']
            )
        );
        $this->assertSame('', $helper->getOrderBy($query, ['Vehicle-Name' => 'ASC']));
        $this->assertSame('', $helper->getOrderBy($query, []));
    }

    public function testTilesAreSortedByTranslatedLabelWithAccents(): void
    {
        $this->getHelper('fr');

        $tiles = static::getContainer()->get(ConfigurationExtension::class)->sortTiles(
            [['html' => 'z', 'label' => 'Zèbre'], ['html' => 'e', 'label' => 'Élan'], ['html' => 'a', 'label' => 'abeille'], ['html' => 'b', 'label' => 'Bison']]
        );

        $this->assertSame(['abeille', 'Bison', 'Élan', 'Zèbre'], array_column($tiles, 'label'));
    }

    public function testTurnstileAttributesAreEscaped(): void
    {
        $this->getHelper('fr');

        $html = static::getContainer()->get(ConfigurationExtension::class)->turnstile_html(['data-action' => '"><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringStartsWith('<div class="cf-turnstile" data-sitekey="', $html);
    }

    private function getHelper(string $locale): ConfigurationHelper
    {
        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->set('default_locale', $locale);

        $request = new Request();
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);

        return static::getContainer()->get(ConfigurationHelper::class);
    }

    private function normalizeSpaces(string $text): string
    {
        return str_replace(["\u{202F}", "\u{A0}"], ' ', $text);
    }
}

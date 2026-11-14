<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Chaque filtre et chaque tri de chaque liste répond sans erreur, et la page garde le filtre choisi
 */
class ListFilterTest extends AppWebTestCase
{
    // page de la liste => route du filtre
    const array LISTS = [
        '/bounty-targets'         => '/bounty-target/filter',
        '/business-goods'         => '/business-good/filter',
        '/businesses'             => '/business/filter',
        '/customizations'         => '/customization/filter',
        '/delivery-methods'       => '/delivery-method/filter',
        '/group-gtas'             => '/group-gta/filter',
        '/login-errors'           => '/login-error/filter',
        '/login-successes'        => '/login-success/filter',
        '/modification-locations' => '/modification-location/filter',
        '/properties'             => '/property/filter',
        '/property-acquisitions'  => '/property-acquisition/filter',
        '/property-types'         => '/property-type/filter',
        '/storage-locations'      => '/storage-location/filter',
        '/title-updates'          => '/title-update/filter',
        '/users'                  => '/user/filter',
        '/vehicle-acquisitions'   => '/vehicle-acquisition/filter',
        '/vehicle-manufacturers'  => '/vehicle-manufacturer/filter',
        '/vehicles'               => '/vehicle/filter',
        '/weapon-acquisitions'    => '/weapon-acquisition/filter',
        '/weapon-manufacturers'   => '/weapon-manufacturer/filter',
        '/weapons'                => '/weapon/filter'
    ];

    // route du filtre => [paramètre, requête donnant une valeur réelle ou valeur fixe]
    const array FILTERS = [
        '/property/filter' => [
            ['businessId', 'SELECT MIN(business_id) FROM property'],
            ['capacityBicycles', 1],
            ['capacityVehicles', 1],
            ['owned', 1],
            ['owned', 0],
            ['propertyAcquisitionIds', ['SELECT MIN(id) FROM property_acquisition']],
            ['propertyAreaId', 'SELECT MIN(property_area_id) FROM property'],
            ['propertyLocationId', 'SELECT MIN(property_location_id) FROM property'],
            ['propertyTypeId', 'SELECT MIN(property_type_id) FROM property'],
            ['titleUpdateId', 'SELECT MIN(title_update_id) FROM property'],
            ['wanted', 1],
            ['wanted', 0]
        ],
        '/vehicle/filter'  => [
            ['evolution', 1],
            ['notStorageLocationIds', ['SELECT MIN(id) FROM storage_location']],
            ['notVehicleAcquisitionIds', ['SELECT MIN(id) FROM vehicle_acquisition']],
            ['notVehicleClassIds', ['SELECT MIN(id) FROM vehicle_class']],
            ['optimized', 1],
            ['owned', 1],
            ['owned', 0],
            ['propertyId', 'SELECT MIN(property_id) FROM vehicle_user'],
            ['seats', 'SELECT MIN(seats) FROM vehicle'],
            ['storageLocationIds', ['SELECT MIN(id) FROM storage_location']],
            ['titleUpdateId', 'SELECT MIN(title_update_id) FROM vehicle'],
            ['vehicleAcquisitionIds', ['SELECT MIN(id) FROM vehicle_acquisition']],
            ['vehicleClassIds', ['SELECT MIN(id) FROM vehicle_class']],
            ['vehicleManufacturerId', 'SELECT MIN(vehicle_manufacturer_id) FROM vehicle'],
            ['vehicleTwo', 1],
            ['wantedProperty', 1],
            ['wantedPropertyId', 'SELECT MIN(wanted_property_id) FROM vehicle_user']
        ],
        '/weapon/filter'   => [
            ['evolution', 1],
            ['notWeaponAcquisitionIds', ['SELECT MIN(id) FROM weapon_acquisition']],
            ['notWeaponClassIds', ['SELECT MIN(id) FROM weapon_class']],
            ['owned', 1],
            ['owned', 0],
            ['titleUpdateId', 'SELECT MIN(title_update_id) FROM weapon'],
            ['weaponAcquisitionIds', ['SELECT MIN(id) FROM weapon_acquisition']],
            ['weaponClassIds', ['SELECT MIN(id) FROM weapon_class']],
            ['weaponManufacturerId', 'SELECT MIN(weapon_manufacturer_id) FROM weapon'],
            ['weaponTwo', 1]
        ]
    ];

    public static function filterProvider(): iterable
    {
        foreach (self::FILTERS as $route => $filters) {
            foreach ($filters as [$parameter, $value]) {
                yield $route . ' ' . $parameter . '=' . json_encode($value) => [$route, $parameter, $value];
            }
        }
    }

    public static function listProvider(): iterable
    {
        foreach (self::LISTS as $page => $route) {
            yield $page => [$page, $route];
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('filterProvider')]
    public function testEveryFilterAnswers(string $route, string $parameter, mixed $value): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        if (is_array($value)) {
            $value = array_map(fn(string $query) => (string)$this->fetchOne($query), $value);
        } elseif (is_string($value)) {
            $value = (string)$this->fetchOne($value);
        }

        $this->client->request('GET', $route, [$parameter => $value]);

        $this->assertFilterSucceeded($route . ' ' . $parameter);
    }

    /**
     * La recherche porte aussi sur les noms liés (fabricant, classe, zone), et reste rapide combinée à un filtre :
     * la sous-requête de recherche ne doit jamais dépendre de la requête principale
     *
     * @throws DoctrineDBALException
     */
    public function testMainListSearchUsesLinkedNamesAndStaysFast(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (
            [
                '/vehicle/filter'  => 'SELECT CONCAT(vehicle_manufacturer.name, " ", vehicle.name), vehicle.name FROM vehicle
                                       INNER JOIN vehicle_manufacturer ON vehicle_manufacturer.id = vehicle.vehicle_manufacturer_id ORDER BY vehicle.id LIMIT 1',
                '/weapon/filter'   => 'SELECT CONCAT(weapon_class_locale.name, " ", weapon_locale.name), weapon_locale.name FROM weapon
                                       INNER JOIN weapon_weapon_class ON weapon_weapon_class.weapon_id = weapon.id
                                       INNER JOIN weapon_class_locale ON weapon_class_locale.weapon_class_id = weapon_weapon_class.weapon_class_id
                                           AND weapon_class_locale.locale_id = 1
                                       INNER JOIN weapon_locale ON weapon_locale.weapon_id = weapon.id AND weapon_locale.locale_id = 1 ORDER BY weapon.id LIMIT 1',
                '/property/filter' => 'SELECT CONCAT(property_area.name, " ", property.name), property.name FROM property
                                       INNER JOIN property_area ON property_area.id = property.property_area_id ORDER BY property.id LIMIT 1'
            ] as $route => $query
        ) {
            $row = $this->connection->fetchNumeric($query);

            $this->assertNotFalse($row, $route . ' : aucun élément de test');

            [$search, $name] = $row;

            $this->client->request('GET', $route, [
                'capacityBicycles' => 'null', 'capacityVehicles' => 'null', 'optimized' => 'null', 'owned' => 'null', 'page' => 1, 'search' => $search,
                'wanted' => 'null', 'wantedProperty' => 'null'
            ]);

            $this->assertFilterSucceeded($route . ' ' . $search);
            $this->assertStringContainsString(
                htmlspecialchars($name), json_decode($this->client->getResponse()->getContent(), true)['template'], $route . ' : "' . $search . '"'
            );
        }

        foreach (
            [
                '/property/filter' => ['search' => 'a', 'wanted' => 1],
                '/vehicle/filter'  => ['evolution' => 4, 'search' => 'a', 'vehicleTwo' => 4, 'wantedProperty' => 1],
                '/weapon/filter'   => ['evolution' => 1, 'owned' => 0, 'search' => 'a']
            ] as $route => $parameters
        ) {
            $start = microtime(true);

            $this->client->request('GET', $route, $parameters);

            $this->assertFilterSucceeded($route . ' ' . json_encode($parameters));
            $this->assertLessThan(2, microtime(true) - $start, $route . ' : recherche combinée trop lente');
        }
    }

    #[DataProvider('listProvider')]
    public function testEverySortAnswers(string $page, string $route): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $sortKeys = $this->client->request('GET', $page)->filter('[data-sort-key][onclick^="orderFilter"]')->each(fn($node) => $node->attr('data-sort-key'));

        $this->assertNotEmpty($sortKeys, $page . ' : aucune colonne triable');

        foreach (array_unique($sortKeys) as $sortKey) {
            foreach (['asc', 'desc'] as $direction) {
                $this->client->request('GET', $route, ['orderNew' => 1, 'orders' => [$sortKey => $direction]]);

                $this->assertFilterSucceeded($page . ' trié par ' . $sortKey . ' ' . $direction);
            }
        }
    }

    #[DataProvider('listProvider')]
    public function testInvalidSortAndPageAreIgnored(string $page, string $route): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (
            [
                ['orderNew' => 1, 'orders' => ['unknown_table-unknown_column' => 'asc']],
                ['orderNew' => 1, 'orders' => ['name; DROP TABLE user' => 'asc']],
                ['orderNew' => 1, 'orders' => ['user-id' => 'sideways']],
                ['orderNew' => 1, 'orders' => ['login_error-ip' => 'asc', 'vehicle-unknown_column' => 'desc']],
                ['page' => -5],
                ['page' => 99999],
                ['page' => PHP_INT_MAX, 'search' => 'zzzzzzzzzz'],
                ['search' => '%_\'"<script>']
            ] as $parameters
        ) {
            $start = microtime(true);

            $this->client->request('GET', $route, $parameters);

            $this->assertFilterSucceeded($page . ' ' . json_encode($parameters));
            $this->assertLessThan(2, microtime(true) - $start, $page . ' ' . json_encode($parameters) . ' : une page hors limites ne doit rien coûter');
        }

        $this->client->request('GET', $page);

        $this->assertResponseIsSuccessful($page . ' doit rester affichable après un filtre invalide');
    }

    /**
     * Une page au-delà de la dernière (filtre ou suppression depuis une page lointaine) revient à la première
     */
    #[DataProvider('listProvider')]
    public function testOutOfRangePageGoesBackToTheFirstPage(string $page, string $route): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $this->client->request('GET', $route, ['page' => 1]);
        $firstPage = json_decode($this->client->getResponse()->getContent(), true)['template'];

        $this->client->request('GET', $route, ['page' => 99999]);

        $this->assertFilterSucceeded($page . ' page 99999');
        $this->assertSame($firstPage, json_decode($this->client->getResponse()->getContent(), true)['template'], $page . ' : retour à la première page');

        $this->client->request('GET', $page);

        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('listProvider')]
    public function testListPageKeepsTheSearch(string $page, string $route): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $this->client->request('GET', $route, ['search' => 'recherche gardée']);
        $crawler = $this->client->request('GET', $page);

        $this->assertResponseIsSuccessful();
        $this->assertSame('recherche gardée', $crawler->filter('#filterSearch')->attr('value'), $page);
    }

    private function assertFilterSucceeded(string $label): void
    {
        $response = $this->client->getResponse();

        $this->assertResponseIsSuccessful($label . ' : ' . mb_substr(strip_tags((string)$response->getContent()), 0, 300));

        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['success'] ?? false, $label);
        $this->assertArrayHasKey('template', $json, $label);
    }
}

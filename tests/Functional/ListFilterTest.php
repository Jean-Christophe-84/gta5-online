<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

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
     * @throws DoctrineDBALException
     */
    public function testUserFiltersRestrictTheList(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $this->connection->insert('user', [
            'active'         => 0,
            'firstname'      => 'Filtre',
            'group_gta_id'   => 4,
            'lastname'       => 'Filtre',
            'locale_id'      => 1,
            'mail'           => 'filter@example.com',
            'password'       => 'x',
            'personal_datas' => 1,
            'roles'          => json_encode(['ROLE_USER']),
            'username'       => 'filtre',
            'verify_code'    => '',
            'verify_mail'    => 1
        ]);
        $otherId = (int)$this->connection->lastInsertId();
        $all     = ['groupGtaId' => 0, 'active' => 'null', 'role' => '0', 'search' => '', 'page' => 1];

        try {
            // La base de test garde les comptes créés par d'autres tests : la liste attendue vient de la base
            foreach (
                [
                    [['groupGtaId' => 4], 'group_gta_id = 4'],
                    [['groupGtaId' => 1], 'group_gta_id = 1'],
                    [['active' => 0], 'active = 0'],
                    [['role' => 'ROLE_ADMIN'], 'JSON_CONTAINS(roles, \'"ROLE_ADMIN"\')'],
                    [['groupGtaId' => 4, 'active' => 0], 'group_gta_id = 4 AND active = 0'],
                    [['groupGtaId' => 1, 'search' => 'filter@example'], 'mail LIKE "%filter@example%"']
                ] as [$parameters, $condition]
            ) {
                $this->client->request('GET', '/user/filter', [...$all, ...$parameters]);

                $response = json_decode($this->client->getResponse()->getContent(), true);
                $ids      = new Crawler($response['template'])->filter('table.users tbody tr')->each(fn(Crawler $row) => (int)substr($row->attr('id'), 5));
                $expected = array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM user WHERE ' . $condition));

                sort($ids);
                sort($expected);

                $this->assertSame($expected, $ids, json_encode($parameters));
            }
        } finally {
            $this->connection->executeStatement('DELETE FROM user WHERE id = ?', [$otherId]);
        }
    }

    /**
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
    public function testSortRanksFollowTheSortOrder(string $page, string $route): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $sortKeys = array_values(array_unique(
            $this->client->request('GET', $page)->filter('[data-sort-key][onclick^="orderFilter"]')->each(fn($node) => $node->attr('data-sort-key'))
        ));

        $this->client->request('GET', $route, ['orderNew' => 1, 'orders' => [end($sortKeys) => 'asc']]);
        $this->assertSame([], $this->getSortRanks(), $page . ' : pas de numéro pour un tri sur une seule colonne');

        if (count($sortKeys) < 2) {
            return;
        }

        $this->client->request('GET', $route, ['orderNew' => 1, 'orders' => [$sortKeys[1] => 'asc']]);
        $this->client->request('GET', $route, ['orderNew' => 0, 'orders' => [$sortKeys[0] => 'desc']]);
        $this->assertEquals([$sortKeys[1] => '1', $sortKeys[0] => '2'], $this->getSortRanks(), $page);
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

    private function getSortRanks(): array
    {
        $template = json_decode($this->client->getResponse()->getContent(), true)['template'];

        return array_column(
            new Crawler($template)->filter('th span.rank')->each(static fn (Crawler $rank): array => [$rank->closest('th')->attr('data-sort-key'), $rank->text()]), 1, 0
        );
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

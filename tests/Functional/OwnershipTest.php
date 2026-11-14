<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

/**
 * Cases « possédé » / « voulu » des listes principales, et ajout / modification par leur formulaire
 */
class OwnershipTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testPropertyCanBeAddedAndEditedByTheForm(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $fields   = $this->getModalFields('/property/get');
        $response = $this->post('/property/form', ['name' => 'Propriété de test', 'price' => '', 'propertyId' => ''] + $fields, '/properties');

        $this->assertFalse($response['success'], 'Le prix est obligatoire');

        $response = $this->post('/property/form', ['name' => 'Propriété de test', 'owned' => 1, 'price' => 150000, 'propertyId' => ''] + $fields, '/properties');

        $this->assertTrue($response['success'], $response['message'] ?? '');

        $propertyId = (int)$this->fetchOne('SELECT id FROM property WHERE name = ?', ['Propriété de test']);

        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = ? AND property_id = ?', [self::USER_ID, $propertyId]));

        $fields   = $this->getModalFields('/property/get', ['propertyId' => $propertyId]);
        $response = $this->post('/property/form', ['name' => 'Propriété modifiée', 'owned' => 0, 'price' => 175000, 'propertyId' => $propertyId] + $fields, '/properties');

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertSame(175000, (int)$this->fetchOne('SELECT price FROM property WHERE id = ?', [$propertyId]));
        $this->assertSame('Propriété modifiée', $this->fetchOne('SELECT name FROM property WHERE id = ?', [$propertyId]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = ? AND property_id = ?', [self::USER_ID, $propertyId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testVehicleCanBeAddedAndEditedByTheForm(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $fields   = ['titleUpdateId' => $this->fetchOne('SELECT MIN(id) FROM title_update')] + $this->getModalFields('/vehicle/get');
        $response = $this->post('/vehicle/form', ['name' => '', 'vehicleId' => ''] + $fields, '/vehicles');

        $this->assertFalse($response['success'], 'Le nom est obligatoire');

        $response = $this->post('/vehicle/form', ['name' => 'Véhicule de test', 'price' => 500000, 'vehicleId' => ''] + $fields, '/vehicles');

        $this->assertTrue($response['success'], $response['message'] ?? '');

        $vehicleId = (int)$this->fetchOne('SELECT id FROM vehicle WHERE name = ?', ['Véhicule de test']);

        $this->assertGreaterThan(0, $vehicleId);

        $fields   = $this->getModalFields('/vehicle/get', ['vehicleId' => $vehicleId]);
        $response = $this->post('/vehicle/form', ['name' => 'Véhicule modifié', 'price' => 650000, 'vehicleId' => $vehicleId] + $fields, '/vehicles');

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertSame('Véhicule modifié', $this->fetchOne('SELECT name FROM vehicle WHERE id = ?', [$vehicleId]));
        $this->assertSame(650000, (int)$this->fetchOne('SELECT price FROM vehicle WHERE id = ?', [$vehicleId]));
    }

    public function testVehicleFormWithoutTitleUpdateIsRefusedCleanly(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $fields   = $this->getModalFields('/vehicle/get');
        $response = $this->post('/vehicle/form', ['name' => 'Véhicule sans mise à jour', 'titleUpdateId' => '', 'vehicleId' => ''] + $fields, '/vehicles');

        $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), 'Un champ obligatoire vide doit donner un message, pas une erreur');
        $this->assertFalse($response['success'] ?? false);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testVehicleOwnershipKeepsItsPropertyUntilSold(): void
    {
        $vehicleId  = (int)$this->fetchOne('SELECT MIN(id) FROM vehicle WHERE evolution = 0 AND id NOT IN (SELECT vehicle_two_id FROM vehicle_vehicle)');
        $propertyId = (int)$this->fetchOne('SELECT MIN(id) FROM property WHERE capacity_vehicles > 0');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('vehicle_user', 'vehicle_id', [$vehicleId => false]);

        $response = $this->post(
            '/vehicle/update', ['owned' => 1, 'propertyId' => $propertyId, 'vehicleId' => $vehicleId, 'wantedPropertyId' => $propertyId], '/vehicles'
        );

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertArrayHasKey('summary', $response);
        $this->assertSame(
            ['owned' => 1, 'property_id' => $propertyId, 'wanted_property_id' => $propertyId],
            array_map(intval(...), $this->connection->fetchAssociative(
                'SELECT owned, property_id, wanted_property_id FROM vehicle_user WHERE user_id = ? AND vehicle_id = ?', [self::USER_ID, $vehicleId]
            ))
        );

        $this->post('/vehicle/update', ['owned' => 0, 'propertyId' => $propertyId, 'vehicleId' => $vehicleId, 'wantedPropertyId' => ''], '/vehicles');

        $this->assertSame(
            ['owned' => 0, 'property_id' => null, 'wanted_property_id' => null],
            $this->connection->fetchAssociative(
                'SELECT owned, property_id, wanted_property_id FROM vehicle_user WHERE user_id = ? AND vehicle_id = ?', [self::USER_ID, $vehicleId]
            ) |> (fn(array $row) => ['owned' => (int)$row['owned'], 'property_id' => $row['property_id'], 'wanted_property_id' => $row['wanted_property_id']])
        );
    }

    public function testUnknownItemIsRefusedCleanly(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (
            [
                '/property/update' => ['owned' => 1, 'propertyId' => 999999, 'wanted' => 0],
                '/user/update'     => ['active' => 1, 'userId' => 999999],
                '/vehicle/update'  => ['owned' => 1, 'vehicleId' => 999999],
                '/weapon/update'   => ['owned' => 1, 'weaponId' => 999999]
            ] as $uri => $parameters
        ) {
            $response = $this->post($uri, $parameters, '/');

            $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $uri);
            $this->assertFalse($response['success'] ?? false, $uri . ' : un élément inconnu doit être refusé');
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testWeaponCanBeAddedAndEditedByTheForm(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $fields   = $this->getModalFields('/weapon/get');
        $response = $this->post('/weapon/form', ['name' => 'Arme de test', 'price' => 25000, 'weaponId' => ''] + $fields, '/weapons');

        $this->assertTrue($response['success'], $response['message'] ?? '');

        $weaponId = (int)$this->fetchOne('SELECT weapon_id FROM weapon_locale WHERE name = ?', ['Arme de test']);

        $this->assertGreaterThan(0, $weaponId);

        $fields   = $this->getModalFields('/weapon/get', ['weaponId' => $weaponId]);
        $response = $this->post('/weapon/form', ['name' => 'Arme modifiée', 'price' => 30000, 'weaponId' => $weaponId] + $fields, '/weapons');

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertSame('Arme modifiée', $this->fetchOne('SELECT name FROM weapon_locale WHERE weapon_id = ?', [$weaponId]));
        $this->assertSame(30000, (int)$this->fetchOne('SELECT price FROM weapon WHERE id = ?', [$weaponId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testWeaponOwnershipIsSaved(): void
    {
        $weaponId = (int)$this->fetchOne('SELECT MIN(id) FROM weapon WHERE evolution = 0 AND id NOT IN (SELECT weapon_two_id FROM weapon_weapon)');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('weapon_user', 'weapon_id', [$weaponId => false]);

        foreach ([1, 0] as $owned) {
            $response = $this->post('/weapon/update', ['owned' => $owned, 'weaponId' => $weaponId], '/weapons');

            $this->assertTrue($response['success'], $response['message'] ?? '');
            $this->assertSame($owned, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = ? AND weapon_id = ?', [self::USER_ID, $weaponId]));
        }
    }
}

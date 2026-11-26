<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class DeletionTest extends AppWebTestCase
{
    const array LISTS = [
        'bounty_target'         => ['/bounty-targets', '/bounty-target/get', 'bountyTargetId'],
        'business'              => ['/businesses', '/business/get', 'businessId'],
        'business_good'         => ['/business-goods', '/business-good/get', 'businessGoodId'],
        'customization'         => ['/customizations', '/customization/get', 'customizationId'],
        'delivery_method'       => ['/delivery-methods', '/delivery-method/get', 'deliveryMethodId'],
        'modification_location' => ['/modification-locations', '/modification-location/get', 'modificationLocationId'],
        'property'              => ['/properties', '/property/get', 'propertyId'],
        'property_acquisition'  => ['/property-acquisitions', '/property-acquisition/get', 'propertyAcquisitionId'],
        'property_type'         => ['/property-types', '/property-type/get', 'propertyTypeId'],
        'storage_location'      => ['/storage-locations', '/storage-location/get', 'storageLocationId'],
        'title_update'          => ['/title-updates', '/title-update/get', 'titleUpdateId'],
        'vehicle'               => ['/vehicles', '/vehicle/get', 'vehicleId'],
        'vehicle_acquisition'   => ['/vehicle-acquisitions', '/vehicle-acquisition/get', 'vehicleAcquisitionId'],
        'vehicle_manufacturer'  => ['/vehicle-manufacturers', '/vehicle-manufacturer/get', 'vehicleManufacturerId'],
        'weapon'                => ['/weapons', '/weapon/get', 'weaponId'],
        'weapon_acquisition'    => ['/weapon-acquisitions', '/weapon-acquisition/get', 'weaponAcquisitionId'],
        'weapon_manufacturer'   => ['/weapon-manufacturers', '/weapon-manufacturer/get', 'weaponManufacturerId']
    ];

    /**
     * Ouvre la modale de suppression d'un élément réel de chaque liste : valide toutes les requêtes de blocage
     *
     * @throws DoctrineDBALException
     */
    public function testEveryListOpensItsDeletionModal(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (self::LISTS as $table => [$page, $get, $parameter]) {
            $id = (int)$this->fetchOne('SELECT id FROM ' . $table . ' ORDER BY id LIMIT 1');

            $this->client->request('GET', $get . '?' . $parameter . '=' . $id . '&delete=1');
            $response = json_decode($this->client->getResponse()->getContent(), true);

            $this->assertTrue($response['success'] ?? false, $table . ' ' . substr($this->client->getResponse()->getContent(), 0, 600));
            $this->assertArrayHasKey('deletable', $response, $table);
            $this->assertStringContainsString('deletion-detail', $response['template'], $table);

            $crawler = $this->client->request('GET', $page);

            $this->assertResponseIsSuccessful($table);
            $this->assertGreaterThan(0, $crawler->filter('button[data-delete]')->count(), $table);
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testLinkedItemsBlockTheDeletion(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $weaponAcquisitionId = (int)$this->fetchOne('SELECT weapon_acquisition_id FROM weapon_weapon_acquisition LIMIT 1');
        $linkedWeapons       = (int)$this->fetchOne(
            'SELECT COUNT(*) FROM weapon_weapon_acquisition WHERE weapon_acquisition_id = ?', [$weaponAcquisitionId]
        );

        $this->client->request('GET', '/weapon-acquisition/get?weaponAcquisitionId=' . $weaponAcquisitionId . '&delete=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($response['deletable']);
        $this->assertStringContainsString((string)$linkedWeapons, $response['template']);

        $response = $this->post('/weapon-acquisition/delete', ['weaponAcquisitionId' => $weaponAcquisitionId], '/weapon-acquisitions');

        $this->assertFalse($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM weapon_acquisition WHERE id = ?', [$weaponAcquisitionId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testUnusedItemIsDeleted(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement("INSERT INTO weapon_manufacturer (name) VALUES ('Deletion test manufacturer')");

        $weaponManufacturerId = (int)$this->connection->lastInsertId();

        $this->client->request('GET', '/weapon-manufacturer/get?weaponManufacturerId=' . $weaponManufacturerId . '&delete=1');
        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['deletable']);

        $response = $this->post('/weapon-manufacturer/delete', ['weaponManufacturerId' => $weaponManufacturerId], '/weapon-manufacturers');

        $this->assertTrue($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM weapon_manufacturer WHERE id = ?', [$weaponManufacturerId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testDeletionRemovesOwnDataAndWarnsAboutUsers(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement('INSERT INTO weapon (evolution) VALUES (0)');

        $weaponId = (int)$this->connection->lastInsertId();

        $this->connection->executeStatement(
            "INSERT INTO weapon_locale (weapon_id, locale_id, name) VALUES (:id, 1, 'Deletion test weapon'), (:id, 2, 'Deletion test weapon')", ['id' => $weaponId]
        );
        $this->connection->executeStatement('INSERT INTO weapon_user (user_id, weapon_id, owned) VALUES (1, :id, 1)', ['id' => $weaponId]);

        $this->client->request('GET', '/weapon/get?weaponId=' . $weaponId . '&delete=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['deletable']);
        $this->assertStringContainsString('deletion-message--warning', $response['template']);

        $response = $this->post('/weapon/delete', ['weaponId' => $weaponId], '/weapons');

        $this->assertTrue($response['success']);

        foreach (['weapon' => 'id', 'weapon_locale' => 'weapon_id', 'weapon_user' => 'weapon_id'] as $table => $column) {
            $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?', [$weaponId]), $table);
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testPropertyDeletionTakesVehiclesOut(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement("INSERT INTO property (name, price) VALUES ('Deletion test property', 0)");

        $propertyId = (int)$this->connection->lastInsertId();
        $vehicleId  = (int)$this->fetchOne('SELECT vehicle_id FROM vehicle_user WHERE user_id = 1 LIMIT 1');

        $this->connection->executeStatement(
            'UPDATE vehicle_user SET property_id = :propertyId WHERE user_id = 1 AND vehicle_id = :vehicleId', ['propertyId' => $propertyId, 'vehicleId' => $vehicleId]
        );

        $response = $this->post('/property/delete', ['propertyId' => $propertyId], '/properties');

        $this->assertTrue($response['success']);
        $this->assertNull($this->fetchOne('SELECT property_id FROM vehicle_user WHERE user_id = 1 AND vehicle_id = ?', [$vehicleId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testDeletionNeedsThePermission(): void
    {
        $this->loginAs(3);

        $weaponManufacturerId = (int)$this->fetchOne('SELECT id FROM weapon_manufacturer ORDER BY id LIMIT 1');

        $crawler = $this->client->request('GET', '/weapon-manufacturers');
        $this->assertCount(0, $crawler->filter('button[data-delete]'));

        $this->client->request('GET', '/weapon-manufacturer/get?weaponManufacturerId=' . $weaponManufacturerId . '&delete=1');
        $this->assertFalse(json_decode($this->client->getResponse()->getContent(), true)['success']);

        $response = $this->post('/weapon-manufacturer/delete', ['weaponManufacturerId' => $weaponManufacturerId], '/weapon-manufacturers');

        $this->assertFalse($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM weapon_manufacturer WHERE id = ?', [$weaponManufacturerId]));
    }

    public function testDeletionModalNeedsAnId(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/weapon-manufacturer/get?weaponManufacturerId=&delete=1');

        $this->assertFalse(json_decode($this->client->getResponse()->getContent(), true)['success']);
    }
}

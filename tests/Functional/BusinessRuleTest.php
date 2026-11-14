<?php

namespace App\Tests\Functional;

class BusinessRuleTest extends AppWebTestCase
{
    const int BUNKER_OWNED_ID     = 166;
    const int BUNKER_NOT_OWNED_ID = 159;

    public function testPropertyCannotExceedBusinessMaximum(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement(
            'UPDATE property_user SET owned = 0, wanted = 0 WHERE user_id = 1 AND property_id IN (
                SELECT id FROM property WHERE business_id = (SELECT business_id FROM property WHERE id = :propertyId)
            )',
            ['propertyId' => self::BUNKER_OWNED_ID]
        );
        $this->setOwned('property_user', 'property_id', [self::BUNKER_OWNED_ID => true]);

        $response = $this->post('/property/update', ['propertyId' => self::BUNKER_NOT_OWNED_ID, 'owned' => 1, 'wanted' => 0], '/properties');

        $this->assertFalse($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = 1 AND property_id = ?', [self::BUNKER_NOT_OWNED_ID]));

        $response = $this->post('/property/update', ['propertyId' => self::BUNKER_OWNED_ID, 'owned' => 1, 'wanted' => 1], '/properties');

        $this->assertTrue($response['success']);
    }

    public function testPropertyTwoIgnoresBusinessMaximum(): void
    {
        [$propertyTwoId, $businessId, $maximumNumber] = array_map(intval(...), $this->connection->fetchNumeric(
            'SELECT property.id, property.business_id, business.maximum_number
            FROM property
            INNER JOIN business ON business.id = property.business_id
            WHERE property.id IN (SELECT property_two_id FROM property_property)
            ORDER BY property.id
            LIMIT 1'
        ));
        $propertyIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM property WHERE business_id = :businessId AND id NOT IN (SELECT property_two_id FROM property_property) ORDER BY id LIMIT ' . $maximumNumber,
            ['businessId' => $businessId]
        );

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement(
            'UPDATE property_user SET owned = 0, wanted = 0 WHERE user_id = 1 AND property_id IN (SELECT id FROM property WHERE business_id = :businessId)',
            ['businessId' => $businessId]
        );
        $this->setOwned('property_user', 'property_id', array_fill_keys(array_map(intval(...), $propertyIds), true));
        $this->setOwned('property_user', 'property_id', [
            (int)$this->fetchOne('SELECT MIN(property_one_id) FROM property_property WHERE property_two_id = ?', [$propertyTwoId]) => true
        ]);

        $response = $this->post('/property/update', ['propertyId' => $propertyTwoId, 'owned' => 1, 'wanted' => 1], '/properties');

        $this->assertTrue($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = 1 AND property_id = ?', [$propertyTwoId]));
    }

    public function testPropertyTwoRequiresAnOwnedParentProperty(): void
    {
        [$propertyTwoId, $parentId, $otherParentId] = array_map(intval(...), $this->connection->fetchNumeric(
            'SELECT property_two_id, MIN(property_one_id), MAX(property_one_id)
            FROM property_property
            GROUP BY property_two_id
            HAVING COUNT(*) > 1
            ORDER BY property_two_id
            LIMIT 1'
        ));

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement(
            'UPDATE property_user SET owned = 0 WHERE user_id = 1 AND property_id IN (
                SELECT property_one_id FROM property_property WHERE property_two_id = :propertyId
            )',
            ['propertyId' => $propertyTwoId]
        );
        $this->setOwned('property_user', 'property_id', [$propertyTwoId => false]);

        $response = $this->post('/property/update', ['propertyId' => $propertyTwoId, 'owned' => 1, 'wanted' => 0], '/properties');

        $this->assertFalse($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = 1 AND property_id = ?', [$propertyTwoId]));

        $this->setOwned('property_user', 'property_id', [$otherParentId => true, $parentId => true]);

        $response = $this->post('/property/update', ['propertyId' => $propertyTwoId, 'owned' => 1, 'wanted' => 0], '/properties');

        $this->assertTrue($response['success']);

        // @note: la sous-propriété reste obtenue tant qu'une autre propriété parente l'est
        $this->post('/property/update', ['propertyId' => $parentId, 'owned' => 0, 'wanted' => 0], '/properties');

        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = 1 AND property_id = ?', [$propertyTwoId]));

        $this->post('/property/update', ['propertyId' => $otherParentId, 'owned' => 0, 'wanted' => 0], '/properties');

        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM property_user WHERE user_id = 1 AND property_id = ?', [$propertyTwoId]));
    }

    public function testWeaponEvolutionReleasesItsBase(): void
    {
        [$baseId, $evolutionId] = $this->getEvolutionPair('weapon');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('weapon_user', 'weapon_id', [$baseId => true, $evolutionId => false]);

        $this->post('/weapon/update', ['weaponId' => $evolutionId, 'owned' => 1], '/weapons');

        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = ?', [$baseId]));
        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = ?', [$evolutionId]));

        $this->post('/weapon/update', ['weaponId' => $baseId, 'owned' => 1], '/weapons');

        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = ?', [$baseId]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = ?', [$evolutionId]));
    }

    public function testVehicleEvolutionReleasesItsBase(): void
    {
        [$baseId, $evolutionId] = $this->getEvolutionPair('vehicle');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('vehicle_user', 'vehicle_id', [$baseId => true, $evolutionId => false]);

        $this->post('/vehicle/update', ['vehicleId' => $evolutionId, 'owned' => 1, 'propertyId' => '', 'wantedPropertyId' => ''], '/vehicles');

        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM vehicle_user WHERE user_id = 1 AND vehicle_id = ?', [$baseId]));
        $this->assertNull($this->fetchOne('SELECT property_id FROM vehicle_user WHERE user_id = 1 AND vehicle_id = ?', [$baseId]));
        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM vehicle_user WHERE user_id = 1 AND vehicle_id = ?', [$evolutionId]));
    }

    public function testSummaryTotalsCountEvolutionsAndBusinessesOnce(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $weapons = (int)$this->fetchOne('SELECT COUNT(*) FROM weapon')
                   - (int)$this->fetchOne(
                       'SELECT COUNT(*) FROM weapon_weapon INNER JOIN weapon ON weapon.id = weapon_weapon.weapon_two_id AND weapon.evolution = 1'
                   );
        $properties = (int)$this->fetchOne(
            'SELECT SUM(slots) FROM (
                SELECT LEAST(COALESCE(MAX(business.maximum_number), 1), COUNT(*)) AS slots
                FROM property
                LEFT JOIN business ON business.id = property.business_id
                WHERE property.id NOT IN (SELECT property_two_id FROM property_property)
                GROUP BY COALESCE(CONCAT("business_", property.business_id), CONCAT("property_", property.id))
            ) slots'
        );

        $this->assertSame($weapons, $this->getSummaryTotal('/weapons'));
        $this->assertSame($properties, $this->getSummaryTotal('/properties'));
    }

    private function getEvolutionPair(string $entity): array
    {
        return array_map(intval(...), $this->connection->fetchNumeric(
            'SELECT ' . $entity . '_one_id, ' . $entity . '_two_id
            FROM ' . $entity . '_' . $entity . '
            INNER JOIN ' . $entity . ' ON ' . $entity . '.id = ' . $entity . '_two_id AND ' . $entity . '.evolution = 1
            ORDER BY ' . $entity . '_two_id
            LIMIT 1'
        ));
    }

    private function getSummaryTotal(string $page): int
    {
        $value = $this->client->request('GET', $page)->filter('.summary-card--owned .summary-card__value')->text();

        return (int)preg_replace('/\D/', '', explode('/', $value)[1]);
    }
}

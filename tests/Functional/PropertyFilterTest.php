<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class PropertyFilterTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testPropertiesAreFilteredByLocation(): void
    {
        $this->loginAs(1);

        [$locationId, $areaId] = array_map(intval(...), $this->connection->fetchNumeric(
            'SELECT property_location_id, property_area_id FROM property WHERE property_location_id IS NOT NULL GROUP BY 1, 2 HAVING COUNT(*) <= 50 LIMIT 1'
        ));

        $response = $this->filterProperties(['propertyLocationId' => $locationId]);

        $this->assertTrue($response['success']);
        $this->assertCount(
            (int)$this->fetchOne('SELECT COUNT(*) FROM property WHERE property_location_id = ?', [$locationId]), $response['properties']
        );

        $response = $this->filterProperties(['propertyAreaId' => $areaId, 'propertyLocationId' => $locationId]);

        $this->assertTrue($response['success']);
        $this->assertCount(
            (int)$this->fetchOne('SELECT COUNT(*) FROM property WHERE property_area_id = ? AND property_location_id = ?', [$areaId, $locationId]),
            $response['properties']
        );
    }

    private function filterProperties(array $parameters): array
    {
        $this->client->request(
            'GET',
            '/property/filter',
            $parameters + [
                'businessId'       => 0,
                'capacityBicycles' => 'null',
                'capacityVehicles' => 'null',
                'owned'            => 'null',
                'page'             => 1,
                'propertyAreaId'   => 0,
                'propertyTypeId'   => 0,
                'search'           => '',
                'titleUpdateId'    => 0,
                'wanted'           => 'null'
            ]
        );

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}

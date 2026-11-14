<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class HeistKortzCenterTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testInvalidValueIsRefusedAndThePageStillWorks(): void
    {
        [$secondaryLootId] = $this->getSecondaryLootWithSeveralNames();
        $otherNameId       = (int)$this->fetchOne(
            'SELECT MIN(id) FROM heist_kortz_center_secondary_loot_name WHERE heist_kortz_center_secondary_loot_id <> ?', [$secondaryLootId]
        );

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement(
            'UPDATE heist_kortz_center SET difficulty = "normal", material = NULL, vehicle = NULL, weapon = NULL WHERE user_id = ?', [self::USER_ID]
        );

        foreach (
            ['difficulty' => 'impossible', 'first_time_in_week' => '2', 'main_loot_id' => 999999, 'material' => 'unknown', 'vehicle' => 'unknown', 'weapon' => 'unknown']
            as $place => $value
        ) {
            $response = $this->post('/heist/kortz-center/form', ['place' => $place, 'value' => $value], '/heist/kortz-center');

            $this->assertFalse($response['success'] ?? false, $place . ' : une valeur invalide doit être refusée');
            $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $place);
        }

        foreach (
            [
                ['type' => 'active', 'value' => '5'],
                ['type' => 'heist_kortz_center_secondary_loot_name_id', 'value' => $otherNameId],
                ['type' => 'price', 'value' => '-10']
            ] as $parameters
        ) {
            $response = $this->post('/heist/kortz-center/form-secondary-loot', $parameters + ['id' => $secondaryLootId], '/heist/kortz-center');

            $this->assertFalse($response['success'] ?? false, $parameters['type'] . ' : une valeur invalide doit être refusée');
            $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $parameters['type']);
        }

        $this->assertSame('normal', $this->fetchOne('SELECT difficulty FROM heist_kortz_center WHERE user_id = ?', [self::USER_ID]));

        foreach (['material' => 'safety-suit', 'vehicle' => 'manchez', 'weapon' => 'street-arsenal'] as $place => $value) {
            $this->assertTrue($this->post('/heist/kortz-center/form', ['place' => $place, 'value' => $value], '/heist/kortz-center')['success'], $place);
        }

        $this->client->request('GET', '/heist/kortz-center');

        $this->assertResponseIsSuccessful();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testSecondaryLootNameCanBeChangedAfterReloadingThePage(): void
    {
        [$secondaryLootId, $firstNameId, $secondNameId] = $this->getSecondaryLootWithSeveralNames();

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->connection->executeStatement(
            'DELETE FROM heist_kortz_center_heist_kortz_center_secondary_loot WHERE user_id = 1 AND heist_kortz_center_secondary_loot_name_id IN (?, ?)',
            [$firstNameId, $secondNameId]
        );
        $this->connection->executeStatement(
            'INSERT INTO heist_kortz_center_heist_kortz_center_secondary_loot (user_id, heist_kortz_center_secondary_loot_name_id, price, active, buyer_request) VALUES (1, ?, 64000, 1, 0)',
            [$secondNameId]
        );

        $crawler = $this->client->request('GET', '/heist/kortz-center');

        $this->assertSame(1, $this->countUserRows($firstNameId, $secondNameId));
        $this->assertSame(
            (string)$secondNameId, $crawler->filter('#heistKortzCenterSecondaryLootNameId_' . $secondaryLootId . ' option[selected]')->attr('value')
        );

        foreach ([$firstNameId, $secondNameId, $secondNameId] as $nameId) {
            $response = $this->post(
                '/heist/kortz-center/form-secondary-loot',
                ['type' => 'heist_kortz_center_secondary_loot_name_id', 'value' => $nameId, 'id' => $secondaryLootId],
                '/heist/kortz-center'
            );

            $this->assertTrue($response['success']);
            $this->assertSame(1, $this->countUserRows($firstNameId, $secondNameId));
            $this->assertSame(
                $nameId,
                (int)$this->fetchOne(
                    'SELECT heist_kortz_center_secondary_loot_name_id FROM heist_kortz_center_heist_kortz_center_secondary_loot WHERE user_id = 1 AND heist_kortz_center_secondary_loot_name_id IN (?, ?)',
                    [$firstNameId, $secondNameId]
                )
            );
        }

        $this->assertSame(64000, (int)$this->fetchOne(
            'SELECT price FROM heist_kortz_center_heist_kortz_center_secondary_loot WHERE user_id = 1 AND heist_kortz_center_secondary_loot_name_id = ?', [$secondNameId]
        ));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function countUserRows(int $firstNameId, int $secondNameId): int
    {
        return (int)$this->fetchOne(
            'SELECT COUNT(*) FROM heist_kortz_center_heist_kortz_center_secondary_loot WHERE user_id = 1 AND heist_kortz_center_secondary_loot_name_id IN (?, ?)',
            [$firstNameId, $secondNameId]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getSecondaryLootWithSeveralNames(): array
    {
        $secondaryLootId = (int)$this->fetchOne(
            'SELECT heist_kortz_center_secondary_loot_id FROM heist_kortz_center_secondary_loot_name GROUP BY heist_kortz_center_secondary_loot_id HAVING COUNT(*) > 1 ORDER BY heist_kortz_center_secondary_loot_id LIMIT 1'
        );

        $this->assertGreaterThan(0, $secondaryLootId, 'No secondary loot with several names in the dump');

        $nameIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM heist_kortz_center_secondary_loot_name WHERE heist_kortz_center_secondary_loot_id = ? ORDER BY id LIMIT 2', [$secondaryLootId]
        );

        return [$secondaryLootId, (int)$nameIds[0], (int)$nameIds[1]];
    }
}

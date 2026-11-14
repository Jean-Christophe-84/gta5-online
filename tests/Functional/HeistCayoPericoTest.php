<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class HeistCayoPericoTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testHardModeAddsTenPercentToTheMainLoot(): void
    {
        [$mainLootId, $mainLootPrice] = $this->getCheapestMainLoot();
        [$safeMinimum, $safeMaximum]  = $this->getSecondaryLootPrices('safe_cash');

        $this->loginAs(1, 'ROLE_ADMIN');

        $this->resetHeist(['difficulty' => 'normal', 'main_loot_id' => $mainLootId]);
        $this->assertSame([$mainLootPrice + $safeMinimum, $mainLootPrice + $safeMaximum], $this->getLootOnTheExit(1));

        $this->resetHeist(['difficulty' => 'hard', 'main_loot_id' => $mainLootId]);

        foreach ([1, 2, 3, 4] as $players) {
            $this->assertSame(
                [(int)round($mainLootPrice * 1.1 + $safeMinimum), (int)round($mainLootPrice * 1.1 + $safeMaximum)], $this->getLootOnTheExit($players)
            );
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testSecondaryLootIsAddedToTheTotal(): void
    {
        [$mainLootId, $mainLootPrice]      = $this->getCheapestMainLoot();
        [$safeMinimum, $safeMaximum]       = $this->getSecondaryLootPrices('safe_cash');
        [$cocaineMinimum, $cocaineMaximum] = $this->getSecondaryLootPrices('cocaine');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setBonusSecondaryLoot($mainLootId, 0);
        $this->resetHeist(['difficulty' => 'normal', 'main_loot_id' => $mainLootId, 'cocaine_zone_one' => 1]);

        $this->assertSame(
            [$mainLootPrice + $cocaineMinimum + $safeMinimum, $mainLootPrice + $cocaineMaximum + $safeMaximum], $this->getLootOnTheExit(1)
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testInvalidValueIsRefusedAndThePageStillWorks(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->resetHeist(['difficulty' => 'normal', 'gold_main_west' => 2]);

        foreach (
            ['difficulty' => 'impossible', 'gold_main_west' => '-3', 'cocaine_zone_one' => 'abc', 'main_loot_id' => 999999, 'weed_zone_six' => '9999999999'] as $place => $value
        ) {
            $response = $this->post('/heist/cayo-perico/form', ['place' => $place, 'value' => $value], '/heist/cayo-perico');

            $this->assertFalse($response['success'] ?? false, $place . ' : une valeur invalide doit être refusée');
            $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $place);
        }

        $this->assertSame('normal', $this->fetchOne('SELECT difficulty FROM heist_cayo_perico WHERE user_id = ?', [self::USER_ID]));
        $this->assertSame(2, (int)$this->fetchOne('SELECT gold_main_west FROM heist_cayo_perico WHERE user_id = ?', [self::USER_ID]));

        $response = $this->post('/heist/cayo-perico/form', ['place' => 'gold_main_west', 'value' => '3'], '/heist/cayo-perico');

        $this->assertTrue($response['success']);
        $this->assertSame(3, (int)$this->fetchOne('SELECT gold_main_west FROM heist_cayo_perico WHERE user_id = ?', [self::USER_ID]));

        $this->client->request('GET', '/heist/cayo-perico');

        $this->assertResponseIsSuccessful();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testMainLootBonusAppliesToSecondaryLootOnly(): void
    {
        [$mainLootId, $mainLootPrice]      = $this->getCheapestMainLoot();
        [$safeMinimum, $safeMaximum]       = $this->getSecondaryLootPrices('safe_cash');
        [$cocaineMinimum, $cocaineMaximum] = $this->getSecondaryLootPrices('cocaine');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setBonusSecondaryLoot($mainLootId, 20);
        $this->resetHeist(['difficulty' => 'hard', 'main_loot_id' => $mainLootId, 'cocaine_zone_one' => 1]);

        $this->assertSame(
            [
                (int)round($mainLootPrice * 1.1 + $cocaineMinimum * 1.2 + $safeMinimum),
                (int)round($mainLootPrice * 1.1 + $cocaineMaximum * 1.2 + $safeMaximum)
            ],
            $this->getLootOnTheExit(1)
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testNoTotalWithoutMainLoot(): void
    {
        [$safeMinimum, $safeMaximum] = $this->getSecondaryLootPrices('safe_cash');

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->resetHeist(['difficulty' => 'hard', 'main_loot_id' => null]);

        $this->assertSame([$safeMinimum, $safeMaximum], $this->getLootOnTheExit(1));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getCheapestMainLoot(): array
    {
        $mainLoot = $this->connection->fetchAssociative('SELECT id, price FROM heist_cayo_perico_main_loot ORDER BY price LIMIT 1');

        return [(int)$mainLoot['id'], (int)$mainLoot['price']];
    }

    /**
     * Reads the "loot on the exit (100 %)" cell for the given number of players.
     */
    private function getLootOnTheExit(int $players): array
    {
        $crawler = $this->client->request('GET', '/heist/cayo-perico');

        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('tr')->reduce(fn($tableRow) => $tableRow->filter('th')->count() === 1 && str_contains($tableRow->filter('th')->text(), '(100'));

        $this->assertCount(1, $row);

        return array_map(fn($amount) => (int)preg_replace('/\D/', '', $amount), explode(' - ', $row->filter('td')->eq($players - 1)->text()));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getSecondaryLootPrices(string $name): array
    {
        $loot = $this->connection->fetchAssociative(
            'SELECT price_minimum, price_maximum FROM heist_cayo_perico_secondary_loot WHERE name = ?', [$name]
        );

        return [(int)$loot['price_minimum'], (int)$loot['price_maximum']];
    }

    /**
     * @throws DoctrineDBALException
     */
    private function setBonusSecondaryLoot(int $mainLootId, int $percentage): void
    {
        $this->connection->executeStatement(
            'UPDATE heist_cayo_perico_main_loot SET percentage_bonus_secondary_loot = ? WHERE id = ?', [$percentage, $mainLootId]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    private function resetHeist(array $values): void
    {
        $columns = $this->connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heist_cayo_perico' AND DATA_TYPE = 'int'
             AND COLUMN_NAME NOT IN ('user_id', 'main_loot_id', 'created_by_id', 'updated_by_id')"
        );

        $values = array_merge(array_fill_keys($columns, 0), $values);
        $sets   = array_map(fn($column) => $column . ' = :' . $column, array_keys($values));

        $this->connection->executeStatement(
            'UPDATE heist_cayo_perico SET ' . implode(', ', $sets) . ' WHERE user_id = :userId', [...$values, 'userId' => self::USER_ID]
        );
    }
}

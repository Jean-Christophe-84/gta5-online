<?php

namespace App\Tests\Functional;

use App\Entity\HeistCasino;
use App\Helper\HeistCasinoHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;

class HeistCasinoTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(
            'UPDATE heist_casino SET approach = NULL, difficulty = NULL, driver_id = NULL, first_time_in_week = NULL, gunman_id = NULL, hacker_id = NULL,
            main_loot_id = NULL WHERE user_id = ?',
            [self::USER_ID]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testDifficultyAndFirstTimeInWeekChangeTheTotal(): void
    {
        [$mainLootId, $price] = $this->getMainLoot();

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setPlace('main_loot_id', $mainLootId);
        $this->setPlace('first_time_in_week', 'null');

        $this->setPlace('difficulty', 'normal');
        $this->assertSame(['amountEliteChallenge' => 50000, 'total' => $price], $this->getProfits());

        $this->setPlace('difficulty', 'hard');
        $this->assertSame(['amountEliteChallenge' => 100000, 'total' => $price * 1.1], $this->getProfits());

        $this->setPlace('first_time_in_week', 0);
        $this->assertSame(['amountEliteChallenge' => 100000, 'total' => $price * 1.1 / 4], $this->getProfits());

        $this->setPlace('first_time_in_week', 1);
        $this->assertSame(['amountEliteChallenge' => 100000, 'total' => $price * 1.1], $this->getProfits());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testEveryPlaceIsSaved(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (
            [
                'approach'   => 'brutal',
                'difficulty' => 'hard',
                'driver_id'  => (int)$this->fetchOne('SELECT MIN(id) FROM heist_casino_driver'),
                'gunman_id'  => (int)$this->fetchOne('SELECT MIN(id) FROM heist_casino_gunman'),
                'hacker_id'  => (int)$this->fetchOne('SELECT MIN(id) FROM heist_casino_hacker'),
                'main_loot_id' => $this->getMainLoot()[0]
            ] as $place => $value
        ) {
            $this->setPlace($place, $value);

            $this->assertSame((string)$value, (string)$this->fetchOne('SELECT ' . $place . ' FROM heist_casino WHERE user_id = ?', [self::USER_ID]), $place);

            $this->setPlace($place, '0');

            $this->assertNull($this->fetchOne('SELECT ' . $place . ' FROM heist_casino WHERE user_id = ?', [self::USER_ID]), $place . ' : 0 vide le choix');
        }
    }

    /**
     * Une valeur hors liste ne doit pas être enregistrée : la page du braquage ne pourrait plus s'afficher
     *
     * @throws DoctrineDBALException
     */
    public function testInvalidValueIsRefusedAndThePageStillWorks(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setPlace('approach', 'brutal');
        $this->setPlace('difficulty', 'normal');

        foreach (['approach' => 'unknown', 'difficulty' => 'impossible', 'driver_id' => 999999, 'main_loot_id' => 999999] as $place => $value) {
            $response = $this->post('/heist/casino/form', ['place' => $place, 'value' => $value], '/heist/casino');

            $this->assertFalse($response['success'] ?? false, $place . ' : une valeur inconnue doit être refusée');
            $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $place);
        }

        $this->client->request('GET', '/heist/casino');

        $this->assertResponseIsSuccessful();
        $this->assertSame('brutal', $this->fetchOne('SELECT approach FROM heist_casino WHERE user_id = ?', [self::USER_ID]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testNoMainLootGivesNoTotal(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setPlace('main_loot_id', '0');
        $this->setPlace('difficulty', 'normal');

        $this->assertEquals(0, $this->getProfits()['total']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testResetEmptiesTheChoices(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setPlace('approach', 'stealthy');
        $this->setPlace('difficulty', 'hard');
        $this->setPlace('first_time_in_week', 1);
        $this->setPlace('main_loot_id', $this->getMainLoot()[0]);

        $response = $this->post('/heist/casino/reset', [], '/heist/casino');

        $this->assertTrue($response['success']);
        $this->assertSame(
            [null, null, null, null, null, null, null],
            array_values($this->connection->fetchAssociative(
                'SELECT approach, difficulty, driver_id, first_time_in_week, gunman_id, hacker_id, main_loot_id FROM heist_casino WHERE user_id = ?',
                [self::USER_ID]
            ))
        );
        $this->assertStringContainsString('style="display: none;"', $this->getResetButton($response['template']));
    }

    public function testResetButtonIsShownAsSoonAsAChoiceIsMade(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        foreach (['difficulty' => 'normal', 'first_time_in_week' => 0] as $place => $value) {
            $response = $this->post('/heist/casino/form', ['place' => $place, 'value' => $value], '/heist/casino');

            $this->assertTrue($response['success'], $place);
            $this->assertStringNotContainsString('style="display: none;"', $this->getResetButton($response['template']), $place);

            $this->post('/heist/casino/reset', [], '/heist/casino');
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getMainLoot(): array
    {
        $mainLoot = $this->connection->fetchAssociative('SELECT id, price FROM heist_casino_main_loot WHERE price > 0 ORDER BY id LIMIT 1');

        return [(int)$mainLoot['id'], (int)$mainLoot['price']];
    }

    private function getProfits(): array
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $heistCasino = $entityManager->getRepository(HeistCasino::class)->findOneBy(['user' => self::USER_ID]);

        return static::getContainer()->get(HeistCasinoHelper::class)->optimizeProfits($heistCasino);
    }

    private function getResetButton(string $template): string
    {
        preg_match('/<a[^>]*id="resetHeistCasino"[^>]*>/', preg_replace('/\s+/', ' ', $template), $matches);

        return $matches[0] ?? '';
    }

    private function setPlace(string $place, int|string $value): void
    {
        $response = $this->post('/heist/casino/form', ['place' => $place, 'value' => $value], '/heist/casino');

        $this->assertTrue($response['success'], $place . ' : ' . ($response['message'] ?? ''));
    }
}

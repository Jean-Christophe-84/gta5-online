<?php

namespace App\Tests\Functional;

use App\Entity\BountyTarget;
use Doctrine\DBAL\Exception as DoctrineDBALException;

class BountyTargetTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testLoggedInGroupOnlySeesTheList(): void
    {
        $this->loginAs(4);
        $crawler = $this->client->request('GET', '/bounty-targets');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('section.summary'));
        $this->assertCount(0, $crawler->filter('input[id^="bountyTargetDone_"]'));
        $this->assertCount(0, $crawler->filter('#addBountyTarget'));
        $this->assertCount(0, $crawler->filter('button[id^="deleteBountyTarget_"]'));

        $bountyTargetId = $this->getBountyTargetId();
        $this->setDone($bountyTargetId, false);

        $response = $this->post('/bounty-target/update', ['bountyTargetId' => $bountyTargetId, 'done' => 1], '/bounty-targets');

        $this->assertFalse($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT done FROM bounty_target_user WHERE user_id = 1 AND bounty_target_id = ?', [$bountyTargetId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testSubscriberChecksTargetsAndSeesSummary(): void
    {
        $this->loginAs(3);
        $this->client->request('GET', '/bounty-target/filter?search=&page=1');
        $this->connection->executeStatement('UPDATE bounty_target_user SET done = 0 WHERE user_id = 1');

        $bountyTargetId = $this->getBountyTargetId();
        $response       = $this->post('/bounty-target/update', ['bountyTargetId' => $bountyTargetId, 'done' => 1], '/bounty-targets');

        $this->assertTrue($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT done FROM bounty_target_user WHERE user_id = 1 AND bounty_target_id = ?', [$bountyTargetId]));

        $crawler = $this->client->request('GET', '/bounty-targets');

        $this->assertCount(1, $crawler->filter('section.summary'));
        $this->assertStringContainsString(
            '1 / ' . $this->fetchOne('SELECT COUNT(*) FROM bounty_target'), $crawler->filter('section.summary .summary-card__value')->first()->text()
        );
        $this->assertCount(0, $crawler->filter('#addBountyTarget'));

        $response = $this->post('/bounty-target/delete', ['bountyTargetId' => $bountyTargetId], '/bounty-targets');

        $this->assertFalse($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM bounty_target WHERE id = ?', [$bountyTargetId]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testModeratorAddsEditsAndDeletesTarget(): void
    {
        $this->loginAs(2);
        $this->connection->executeStatement("DELETE FROM bounty_target_user WHERE bounty_target_id IN (SELECT id FROM bounty_target WHERE name LIKE 'Test target%')");
        $this->connection->executeStatement("DELETE FROM bounty_target WHERE name LIKE 'Test target%'");

        $response = $this->post(
            '/bounty-target/form',
            ['name' => 'Test target', 'bountyMissionId' => '', 'mostWanted' => 1, 'done' => 1, 'bountyTargetId' => ''],
            '/bounty-targets'
        );

        $this->assertTrue($response['success']);

        $bountyTargetId = (int)$this->fetchOne("SELECT id FROM bounty_target WHERE name = 'Test target'");

        $this->assertSame(1, (int)$this->fetchOne('SELECT most_wanted FROM bounty_target WHERE id = ?', [$bountyTargetId]));
        $this->assertSame(1, (int)$this->fetchOne('SELECT done FROM bounty_target_user WHERE user_id = 1 AND bounty_target_id = ?', [$bountyTargetId]));

        $response = $this->post(
            '/bounty-target/form',
            ['name' => 'Test target edited', 'bountyMissionId' => '', 'mostWanted' => 0, 'done' => 0, 'bountyTargetId' => $bountyTargetId],
            '/bounty-targets'
        );

        $this->assertTrue($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT most_wanted FROM bounty_target WHERE id = ?', [$bountyTargetId]));
        $this->assertSame('Test target edited', $this->fetchOne('SELECT name FROM bounty_target WHERE id = ?', [$bountyTargetId]));

        $response = $this->post('/bounty-target/delete', ['bountyTargetId' => $bountyTargetId], '/bounty-targets');

        $this->assertTrue($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM bounty_target WHERE id = ?', [$bountyTargetId]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM bounty_target_user WHERE bounty_target_id = ?', [$bountyTargetId]));
    }

    public function testFormRequiresName(): void
    {
        $this->loginAs(2);

        $response = $this->post('/bounty-target/form', ['name' => '', 'bountyMissionId' => '', 'mostWanted' => 0, 'bountyTargetId' => ''], '/bounty-targets');

        $this->assertFalse($response['success']);
    }

    /**
     * La prime ne dépend que du drapeau most_wanted : fourchette fixe par catégorie, la prime morte est le quart affiché sur la liste
     *
     * @throws DoctrineDBALException
     */
    public function testPriceDependsOnlyOnMostWanted(): void
    {
        $this->loginAs(4);

        foreach ([0 => [BountyTarget::PRICE_MIN, BountyTarget::PRICE_MAX], 1 => [BountyTarget::PRICE_MOST_WANTED_MIN, BountyTarget::PRICE_MOST_WANTED_MAX]] as $mostWanted => $prices) {
            $name = $this->fetchOne('SELECT name FROM bounty_target WHERE most_wanted = ? ORDER BY id LIMIT 1', [$mostWanted]);

            $this->client->request('GET', '/bounty-target/filter?search=' . urlencode($name) . '&page=1');
            $bountyTargets = array_column(json_decode($this->client->getResponse()->getContent(), true)['bountyTargets'], null, 'name');

            $this->assertSame($prices, [(int)$bountyTargets[$name]['priceMin'], (int)$bountyTargets[$name]['priceMax']]);
        }

        // Un tri enregistré sur la prime reste valable sans colonne de prix
        $this->client->request('GET', '/bounty-target/filter?search=&page=1&orders[bounty_target-price]=desc&orderNew=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertSame(BountyTarget::PRICE_MOST_WANTED_MIN, (int)$response['bountyTargets'][0]['priceMin']);

        $this->client->request('GET', '/bounty-target/filter?search=&page=1&orders[bounty_target-name]=asc&orderNew=1');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testTargetsCanBeSearchedByNameAndMission(): void
    {
        $this->loginAs(4);

        $name = $this->fetchOne('SELECT name FROM bounty_target ORDER BY id LIMIT 1');

        $this->client->request('GET', '/bounty-target/filter?search=' . urlencode($name) . '&page=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertContains($name, array_column($response['bountyTargets'], 'name'));

        $mission = $this->fetchOne(
            'SELECT bounty_mission_locale.name FROM bounty_mission_locale
             INNER JOIN user ON user.locale_id = bounty_mission_locale.locale_id AND user.id = 1
             INNER JOIN bounty_target ON bounty_target.bounty_mission_id = bounty_mission_locale.bounty_mission_id LIMIT 1'
        );

        $this->client->request('GET', '/bounty-target/filter?search=' . urlencode($mission) . '&page=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(
            (int)$this->fetchOne(
                'SELECT COUNT(*) FROM bounty_target INNER JOIN bounty_mission_locale ON bounty_mission_locale.bounty_mission_id = bounty_target.bounty_mission_id
                 WHERE bounty_mission_locale.name = ?', [$mission]
            ),
            count($response['bountyTargets'])
        );

        $this->client->request('GET', '/bounty-target/filter?search=&page=1');
    }

    public function testHomepageLinksToTargets(): void
    {
        $this->loginAs(4);
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/bounty-targets"]');
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getBountyTargetId(): int
    {
        return (int)$this->fetchOne('SELECT id FROM bounty_target ORDER BY id LIMIT 1');
    }

    /**
     * @throws DoctrineDBALException
     */
    private function setDone(int $bountyTargetId, bool $done): void
    {
        $this->connection->executeStatement(
            'INSERT INTO bounty_target_user (user_id, bounty_target_id, done) VALUES (:userId, :id, :done) ON DUPLICATE KEY UPDATE done = :done',
            ['done' => (int)$done, 'id' => $bountyTargetId, 'userId' => self::USER_ID]
        );
    }
}

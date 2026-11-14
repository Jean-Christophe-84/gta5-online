<?php

namespace App\Tests\Functional;

class SecurityTest extends AppWebTestCase
{
    public function testMutationRouteRejectsGet(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/weapon/update?weaponId=72&owned=1');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('weapon_user', 'weapon_id', [72 => false]);

        $this->client->request('POST', '/weapon/update', ['weaponId' => 72, 'owned' => 1]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = 72'));
    }

    public function testPostWithInvalidCsrfTokenIsRejected(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('POST', '/weapon/update', ['weaponId' => 72, 'owned' => 1], [], ['HTTP_X_CSRF_TOKEN' => 'invalid']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPostWithValidCsrfTokenSucceeds(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->setOwned('weapon_user', 'weapon_id', [72 => false]);

        $response = $this->post('/weapon/update', ['weaponId' => 72, 'owned' => 1], '/weapons');

        $this->assertTrue($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM weapon_user WHERE user_id = 1 AND weapon_id = 72'));
    }

    public function testUsersPageIsReservedToAdmin(): void
    {
        $this->loginAs(2);
        $this->client->request('GET', '/users');
        $this->assertResponseStatusCodeSame(403);

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/users');
        $this->assertResponseIsSuccessful();
    }

    public function testDisabledAccountLosesItsOpenSession(): void
    {
        $this->loginAs(4);
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        try {
            $this->connection->executeStatement('UPDATE user SET active = 0 WHERE id = :userId', ['userId' => self::USER_ID]);

            $this->client->request('GET', '/');

            $this->assertResponseRedirects('/login');
        } finally {
            $this->connection->executeStatement('UPDATE user SET active = 1 WHERE id = :userId', ['userId' => self::USER_ID]);
        }
    }

    public function testNamesAreEscapedInBuiltHtml(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $driverId = (int)$this->fetchOne('SELECT heist_casino_driver_id FROM heist_casino_driver_vehicle LIMIT 1');
        $name     = $this->fetchOne('SELECT name FROM heist_casino_driver WHERE id = ?', [$driverId]);

        try {
            $this->connection->executeStatement("UPDATE heist_casino_driver SET name = '<b id=\"injected\">x</b>' WHERE id = :id", ['id' => $driverId]);

            $crawler = $this->client->request('GET', '/heist/casino');

            $this->assertResponseIsSuccessful();
            $this->assertCount(0, $crawler->filter('#injected'));
            $this->assertStringContainsString('&lt;b id=&quot;injected&quot;&gt;', $this->client->getResponse()->getContent());
        } finally {
            $this->connection->executeStatement('UPDATE heist_casino_driver SET name = :name WHERE id = :id', ['id' => $driverId, 'name' => $name]);
        }
    }

    public function testUnknownHeistFieldIsABadRequest(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->post('/heist/casino/form', ['place' => 'user_id', 'value' => 2], '/heist/casino');

        $this->assertResponseStatusCodeSame(400);
    }
}

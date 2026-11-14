<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class UserStatisticTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testStatisticsAreReservedToAdmin(): void
    {
        $this->loginAs(2);
        $this->client->request('GET', '/user/statistics');

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testStatisticsByHourForASingleDay(): void
    {
        $this->connection->executeStatement('DELETE FROM user_statistic');
        $this->connection->executeStatement(
            "INSERT INTO user_statistic (year, month, day, hour, number_users) VALUES ('2026', '01', '15', '10', 4), ('2026', '01', '15', null, 9)"
        );

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/user/statistics?start=2026-01-15&end=2026-01-15');
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertTrue($response['filter']);
        $this->assertCount(24, $response['data']);
        $this->assertSame(4, $response['data'][10]);
        $this->assertSame(0, $response['data'][11]);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testStatisticsByDayMonthAndYear(): void
    {
        $this->connection->executeStatement('DELETE FROM user_statistic');
        $this->connection->executeStatement(
            "INSERT INTO user_statistic (year, month, day, hour, number_users)
             VALUES ('2025', '03', '02', null, 5), ('2025', '03', null, null, 12), ('2025', null, null, null, 30)"
        );

        $this->loginAs(1, 'ROLE_ADMIN');

        // Dates inversées : remises dans l'ordre
        $this->client->request('GET', '/user/statistics?start=2025-03-07&end=2025-03-01');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['2025-03-01', '2025-03-07'], [$response['start'], $response['end']]);
        $this->assertSame([0, 5, 0, 0, 0, 0, 0], $response['data']);

        $this->client->request('GET', '/user/statistics?start=2025-01-01&end=2025-12-31');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(12, $response['data']);
        $this->assertSame(12, $response['data'][2]);

        $this->client->request('GET', '/user/statistics?start=2020-06-01&end=2025-12-31');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['2020', '2021', '2022', '2023', '2024', '2025'], $response['categories']);
        $this->assertSame(30, $response['data'][5]);

        // Sans paramètre : la dernière période reste en session
        $this->client->request('GET', '/user/statistics');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('2020-06-01', $response['start']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testRequestUpdatesLastActivity(): void
    {
        $this->connection->executeStatement(
            "UPDATE user SET last_activity = NULL, updated_at = '2020-01-01 00:00:00', updated_by_id = NULL WHERE id = :userId", ['userId' => self::USER_ID]
        );

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/users');

        $user = $this->connection->fetchAssociative('SELECT last_activity, updated_at, updated_by_id FROM user WHERE id = :userId', ['userId' => self::USER_ID]);

        $this->assertNotNull($user['last_activity']);
        // Seule la date d'activité change : updated_at / updated_by restent ceux de la dernière vraie modification du compte
        $this->assertSame(['2020-01-01 00:00:00', null], [$user['updated_at'], $user['updated_by_id']]);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testLastActivityIsWrittenAtMostOncePerDelay(): void
    {
        // Un appel AJAX compte comme un clic (connexion après la mise en place : l'utilisateur chargé porte cette date)
        $this->connection->executeStatement("UPDATE user SET last_activity = NOW() - INTERVAL 2 MINUTE WHERE id = :userId", ['userId' => self::USER_ID]);
        $old = $this->fetchOne('SELECT last_activity FROM user WHERE id = :userId', ['userId' => self::USER_ID]);

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/user/filter?groupGtaId=0&active=null&role=0&search=&page=1');
        $this->assertGreaterThan($old, $this->fetchOne('SELECT last_activity FROM user WHERE id = :userId', ['userId' => self::USER_ID]));

        // Moins de REFRESH_DELAY secondes après : pas de nouvelle écriture
        $this->connection->executeStatement("UPDATE user SET last_activity = NOW() - INTERVAL 10 SECOND WHERE id = :userId", ['userId' => self::USER_ID]);
        $recent = $this->fetchOne('SELECT last_activity FROM user WHERE id = :userId', ['userId' => self::USER_ID]);
        $this->client->request('GET', '/users');
        $this->assertSame($recent, $this->fetchOne('SELECT last_activity FROM user WHERE id = :userId', ['userId' => self::USER_ID]));
    }
}

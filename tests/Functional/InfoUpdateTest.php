<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class InfoUpdateTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testSqlInsertFillsCreatedAndUpdatedColumns(): void
    {
        $this->connection->executeStatement('DELETE FROM weapon_user WHERE user_id = :userId', ['userId' => self::USER_ID]);

        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/weapons');

        $this->assertSame(0, (int)$this->fetchOne(
            'SELECT COUNT(*) FROM weapon_user
             WHERE user_id = :userId AND (created_at IS NULL OR created_by_id <> :userId OR updated_at IS NULL OR updated_by_id <> :userId)',
            ['userId' => self::USER_ID]
        ));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testSqlUpdateFillsUpdatedColumns(): void
    {
        $this->connection->executeStatement('UPDATE weapon_user SET updated_at = NULL, updated_by_id = NULL WHERE user_id = :userId', ['userId' => self::USER_ID]);
        $this->connection->executeStatement('UPDATE heist_casino SET updated_at = NULL, updated_by_id = NULL WHERE user_id = :userId', ['userId' => self::USER_ID]);
        $this->connection->executeStatement('UPDATE user SET updated_at = NULL, updated_by_id = NULL WHERE id = :userId', ['userId' => self::USER_ID]);

        $this->loginAs(1, 'ROLE_ADMIN');

        $this->post('/weapon/update', ['weaponId' => 72, 'owned' => 1], '/weapons');
        $this->assertSame(
            [self::USER_ID, true],
            $this->getUpdatedInfo('SELECT updated_by_id, updated_at FROM weapon_user WHERE user_id = :userId AND weapon_id = 72')
        );

        $this->post('/heist/casino/form', ['place' => 'difficulty', 'value' => 'hard'], '/heist/casino');
        $this->assertSame([self::USER_ID, true], $this->getUpdatedInfo('SELECT updated_by_id, updated_at FROM heist_casino WHERE user_id = :userId'));

        $this->post('/user/update', ['userId' => self::USER_ID, 'active' => 1], '/users');
        $this->assertSame([self::USER_ID, true], $this->getUpdatedInfo('SELECT updated_by_id, updated_at FROM user WHERE id = :userId'));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getUpdatedInfo(string $query): array
    {
        $row = $this->connection->fetchAssociative($query, ['userId' => self::USER_ID]);

        return [(int)$row['updated_by_id'], $row['updated_at'] !== null];
    }
}

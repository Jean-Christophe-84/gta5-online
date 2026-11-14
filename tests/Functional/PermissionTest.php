<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Security\Voter\PermissionVoter;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PermissionTest extends AppWebTestCase
{
    public function testLoggedInGroupSeesNoPersonalData(): void
    {
        $this->loginAs(4);
        $crawler = $this->client->request('GET', '/vehicles');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('section.summary'));
        $this->assertCount(0, $crawler->filter('input[id^="vehicleOwned_"]'));
        $this->assertCount(0, $crawler->filter('#addVehicle'));
    }

    public function testLoggedInGroupCannotUpdateOwnership(): void
    {
        $this->setOwned('vehicle_user', 'vehicle_id', [305 => true]);
        $this->loginAs(4);
        $response = $this->post('/vehicle/update', ['vehicleId' => 305, 'owned' => 0], '/vehicles');

        $this->assertFalse($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT owned FROM vehicle_user WHERE user_id = 1 AND vehicle_id = 305'));
    }

    public function testSubscriberSeesPersonalDataButNoExternalData(): void
    {
        $this->loginAs(3);
        $crawler = $this->client->request('GET', '/vehicles');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('section.summary'));
        $this->assertGreaterThan(0, $crawler->filter('input[id^="vehicleOwned_"]')->count());
    }

    public function testNonAdminGetsNoCommandInformation(): void
    {
        $this->loginAs(2);

        foreach (['/' => 'promotion', '/properties' => 'property', '/vehicles' => 'vehicle', '/weapons' => 'weapon'] as $page => $entity) {
            $this->client->request('GET', $page);

            $this->assertResponseIsSuccessful();
            $this->assertStringNotContainsString('/' . $entity . '/launch-command', $this->client->getResponse()->getContent());
            $this->assertStringNotContainsString('/' . $entity . '/check-command', $this->client->getResponse()->getContent());
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testHomepageHidesListsWithoutViewPermission(): void
    {
        $this->setPermission('VEHICLE_VIEW', 4, false);

        try {
            $this->loginAs(4);
            $this->client->request('GET', '/');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorNotExists('a[href="/vehicles"]');
            $this->assertSelectorExists('a[href="/weapons"]');
        } finally {
            $this->setPermission('VEHICLE_VIEW', 4, true);
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testAdminListsCanBeSearched(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $this->client->request('GET', '/user/filter?groupGtaId=0&active=null&role=0&search=' . urlencode('sanna proton') . '&page=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['users']);

        $this->client->request('GET', '/group-gta/filter?search=' . urlencode('utilisateur pay') . '&page=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertSame([3], array_column($response['groupGtas'], 'id'));
    }

    /**
     * Le voter ne se prononce que sur les permissions : ROLE_* reste l'affaire des autres voters
     *
     * @throws DoctrineDBALException
     */
    public function testPermissionVoterOnlyVotesOnPermissions(): void
    {
        $this->loginAs(4);

        $voter = static::getContainer()->get(PermissionVoter::class);
        $token = new UsernamePasswordToken(static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->find(self::USER_ID), 'main');

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['ROLE_ADMIN']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['VEHICLE_VIEW']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['VEHICLE_EDIT']));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function setPermission(string $attribute, int $groupGtaId, bool $active): void
    {
        $this->connection->executeStatement(
            'UPDATE permission_group_gta INNER JOIN permission ON permission.id = permission_group_gta.permission_id
             SET permission_group_gta.active = :active WHERE permission.attribute = :attribute AND permission_group_gta.group_gta_id = :groupGtaId',
            ['active' => (int)$active, 'attribute' => $attribute, 'groupGtaId' => $groupGtaId]
        );
    }
}

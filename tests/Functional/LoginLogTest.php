<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginLogTest extends AppWebTestCase
{
    const string PASSWORD = 'MotDePasse-2026';

    /**
     * @throws DoctrineDBALException
     */
    public function testFailedFormLoginIsLogged(): void
    {
        $this->connection->executeStatement('DELETE FROM login_error');

        $this->submitLogin('inconnu@example.com', 'mauvaismotdepasse');

        $row = $this->connection->fetchAssociative('SELECT email, ip FROM login_error');

        $this->assertSame('inconnu@example.com', $row['email']);
        $this->assertNotSame('', $row['ip']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testSuccessfulFormLoginIsLogged(): void
    {
        $this->connection->executeStatement('DELETE FROM login_success');
        $mail = $this->setKnownPassword();

        $this->submitLogin($mail, self::PASSWORD);

        $this->assertSame(self::USER_ID, (int)$this->fetchOne('SELECT user_id FROM login_success'));
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM login_error WHERE email = ?', [$mail]));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testLoginWithoutTurnstileIsRefusedAndLogged(): void
    {
        $this->connection->executeStatement('DELETE FROM login_error');
        $this->connection->executeStatement('DELETE FROM login_success');
        $mail = $this->setKnownPassword();

        $this->submitLogin($mail, self::PASSWORD, null);

        $this->assertStringContainsString('vérification anti-robot', $this->client->followRedirect()->filter('.alert-danger')->text());
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM login_success'));
        $this->assertSame($mail, $this->fetchOne('SELECT email FROM login_error'));

        $this->client->request('GET', '/');
        $this->assertResponseRedirects('/login');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testTurnstileErrorDoesNotRevealTheAccount(): void
    {
        $mail = $this->setKnownPassword();

        $this->submitLogin($mail, self::PASSWORD, null);
        $known = $this->client->followRedirect()->filter('.alert-danger')->text();

        $this->submitLogin('inconnu@example.com', self::PASSWORD, null);

        $this->assertSame($known, $this->client->followRedirect()->filter('.alert-danger')->text());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testAutomaticLoginAfterMailValidationIsNotLogged(): void
    {
        $this->connection->executeStatement('DELETE FROM login_success');

        $this->post(
            '/user/register',
            ['lastname' => 'Nom', 'firstname' => 'Prénom', 'username' => 'Pseudo', 'mail' => 'log@example.com', 'password' => self::PASSWORD, 'personalDatas' => '1'],
            '/login'
        );
        preg_match('#/user/verify-mail/(\d+)/([a-f0-9]{64})#', $this->getMailerMessage()->getHtmlBody(), $matches);

        $this->assertTrue($this->post('/user/verify-mail', ['userId' => $matches[1], 'code' => $matches[2]], '/login')['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM login_success'));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testListsAreReservedToAdmin(): void
    {
        foreach (['/login-errors', '/login-successes', '/login-error/filter', '/login-success/filter'] as $uri) {
            $this->client->request('GET', $uri);
            $this->assertResponseRedirects('/login', null, $uri);
        }

        $this->loginAs(2);

        foreach (['/login-errors', '/login-successes'] as $uri) {
            $this->client->request('GET', $uri);
            $this->assertResponseStatusCodeSame(403);
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testListsShowAndFilterTheLogins(): void
    {
        $this->connection->executeStatement('DELETE FROM login_error');
        $this->connection->executeStatement(
            "INSERT INTO login_error (email, date, ip) VALUES ('first@example.com', NOW(), '10.0.0.1'), ('second@example.com', NOW(), '10.0.0.2')"
        );
        $this->connection->executeStatement('DELETE FROM login_success');
        $this->connection->executeStatement("INSERT INTO login_success (user_id, date, ip) VALUES (1, NOW(), '10.0.0.3')");

        $this->loginAs(1, 'ROLE_ADMIN');

        $this->client->request('GET', '/login-errors');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, 'table.loginErrors tbody tr');

        $this->client->request('GET', '/login-error/filter?search=second&page=1');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('second@example.com', $response['template']);
        $this->assertStringNotContainsString('first@example.com', $response['template']);

        $this->client->request('GET', '/login-successes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('table.loginSuccesses tbody', '10.0.0.3');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testOldLoginsAreRemoved(): void
    {
        $this->connection->executeStatement('DELETE FROM login_error');
        $this->connection->executeStatement(
            "INSERT INTO login_error (email, date, ip) VALUES ('old@example.com', NOW() - INTERVAL 13 MONTH, '10.0.0.1'), ('recent@example.com', NOW(), '10.0.0.2')"
        );

        $command = new CommandTester(new Application(static::$kernel)->find('logins:remove-old'));
        $command->execute([]);

        $command->assertCommandIsSuccessful();
        $this->assertSame(['recent@example.com'], $this->connection->fetchFirstColumn('SELECT email FROM login_error'));
    }

    /**
     * @throws DoctrineDBALException
     */
    private function setKnownPassword(): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user          = $entityManager->getRepository(User::class)->find(self::USER_ID);
        $password      = static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD);

        $this->connection->executeStatement('UPDATE user SET password = ?, active = 1, verify_mail = 1 WHERE id = ?', [$password, self::USER_ID]);

        return $user->getMail();
    }
}

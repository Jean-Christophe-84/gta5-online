<?php

namespace App\Tests\Functional;

use App\EventListener\BackupListener;
use App\Helper\BackupHelper;
use App\Service\AfterResponse;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sauvegarde de la base après chaque modification des données (backup en dev, var/backup_test en test)
 */
class BackupTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testSavingBacksUpTheDatabase(): void
    {
        $this->connection->executeStatement('UPDATE user SET locale_id = (SELECT id FROM locale WHERE code = "fr") WHERE id = ?', [self::USER_ID]);
        $this->loginAs(1, 'ROLE_ADMIN');

        try {
            $token = $this->client->request('GET', '/vehicles')->filter('#localeSwitcherMenu input[name="_token"]')->attr('value');
            $this->clearBackups();

            $this->client->request('POST', '/locale/switch', ['_target_path' => '/vehicles', '_token' => $token, 'code' => 'en']);

            $this->assertResponseRedirects('/vehicles');
            $this->assertCount(1, $this->getHelper()->getHistory());
            $this->assertStringContainsString('CREATE TABLE `user`', file_get_contents($this->getHelper()->getLatestPath()));
        } finally {
            $this->connection->executeStatement('UPDATE user SET locale_id = (SELECT id FROM locale WHERE code = "fr") WHERE id = ?', [self::USER_ID]);
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testActivityOfALoggedInUserDoesNotBackUpTheDatabase(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->client->request('GET', '/vehicles');
        $this->connection->executeStatement('UPDATE user SET last_activity = ? WHERE id = ?', ['2020-01-01 00:00:00', self::USER_ID]);
        $this->loginAs(1, 'ROLE_ADMIN');
        $this->clearBackups();

        $this->client->request('GET', '/vehicles');

        $this->assertResponseIsSuccessful();
        $this->assertNotSame('2020-01-01 00:00:00', $this->fetchOne('SELECT last_activity FROM user WHERE id = ?', [self::USER_ID]));
        $this->assertFileDoesNotExist($this->getHelper()->getLatestPath());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testLoginLogDoesNotBackUpTheDatabase(): void
    {
        static::getContainer()->get(BackupListener::class)->ignore(fn() => $this->connection->executeStatement('DELETE FROM login_error'));
        $this->clearBackups();

        $this->submitLogin('inconnu@example.com', 'mauvaismotdepasse');

        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM login_error'));
        $this->assertFileDoesNotExist($this->getHelper()->getLatestPath());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testWriteAfterTheResponseIsBackedUp(): void
    {
        $this->connection->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS backup_write (id INT)');
        $this->clearBackups();

        static::getContainer()->get(AfterResponse::class)->add(
            fn() => $this->connection->executeStatement('INSERT INTO backup_write (id) VALUES (?)', [1])
        );
        $this->terminate();

        $this->assertCount(1, $this->getHelper()->getHistory());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testQueryChangingNoDataDoesNotBackUpTheDatabase(): void
    {
        $this->clearBackups();

        $this->fetchOne('SELECT COUNT(*) FROM vehicle');
        $this->connection->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS backup_schema (id INT)');
        $this->connection->executeStatement('UPDATE vehicle SET name = name WHERE 1 = 0');
        $this->connection->executeStatement('UPDATE vehicle SET name = name');
        $this->terminate();

        $this->assertFileDoesNotExist($this->getHelper()->getLatestPath());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testIgnoredWriteDoesNotBackUpTheDatabase(): void
    {
        $this->connection->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS backup_ignored (id INT)');
        $this->clearBackups();

        static::getContainer()->get(BackupListener::class)->ignore(
            fn() => $this->connection->executeStatement('INSERT INTO backup_ignored (id) VALUES (?)', [1])
        );
        $this->terminate();

        $this->assertFileDoesNotExist($this->getHelper()->getLatestPath());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testTrackingCommandDoesNotBackUpTheDatabase(): void
    {
        $this->connection->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS backup_write (id INT)');

        foreach (BackupListener::SKIPPED_COMMANDS as $name) {
            $this->clearBackups();

            $this->connection->executeStatement('INSERT INTO backup_write (id) VALUES (?)', [1]);
            $this->terminateCommand($name);

            $this->assertFileDoesNotExist($this->getHelper()->getLatestPath(), $name);
        }

        $this->connection->executeStatement('INSERT INTO backup_write (id) VALUES (?)', [1]);
        $this->terminateCommand('users:purge');

        $this->assertCount(1, $this->getHelper()->getHistory());
    }

    public function testCommandBacksUpTheDatabase(): void
    {
        $tester = new CommandTester(new Application(static::$kernel)->find('app:backup'));
        $this->clearBackups();

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Sauvegarde ajoutée : ' . $this->getHelper()->getHistory()[0], $tester->getDisplay());

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('La base n\'a pas changé depuis la dernière sauvegarde', $tester->getDisplay());
        $this->assertCount(1, $this->getHelper()->getHistory());
    }

    private function clearBackups(): void
    {
        new Filesystem()->remove(static::getContainer()->getParameter('backup_directory'));
    }

    private function getHelper(): BackupHelper
    {
        return static::getContainer()->get(BackupHelper::class);
    }

    private function terminate(): void
    {
        static::getContainer()->get('event_dispatcher')->dispatch(new TerminateEvent(static::$kernel, new Request(), new Response()), KernelEvents::TERMINATE);
    }

    private function terminateCommand(string $name): void
    {
        static::getContainer()->get('event_dispatcher')->dispatch(
            new ConsoleTerminateEvent(new Command($name), new ArrayInput([]), new NullOutput(), Command::SUCCESS), ConsoleEvents::TERMINATE
        );
    }
}

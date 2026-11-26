<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Helper\UserHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

class AccountTest extends AppWebTestCase
{
    const string PASSWORD = 'MotDePasse-2026';

    /**
     * @throws DoctrineDBALException
     */
    public function testRegisterCreatesAnUnverifiedAccountInTheDefaultGroup(): void
    {
        $response = $this->register('register@example.com');

        $this->assertTrue($response['success']);
        $this->assertEmailCount(1);

        $user = $this->connection->fetchAssociative('SELECT * FROM user WHERE mail = ?', ['register@example.com']);

        $this->assertSame((int)$this->fetchOne('SELECT id FROM group_gta WHERE `default` = 1'), (int)$user['group_gta_id']);
        $this->assertSame('ne-pas-repondre@gta5-online.fr', $this->getMailerMessage()->getFrom()[0]->getAddress());
        $this->assertSame(['ROLE_USER'], json_decode($user['roles'], true));
        $this->assertSame([1, 0, 0], [(int)$user['active'], (int)$user['verify_mail'], (int)$user['verify_attempts']]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $user['verify_code']);
        $this->assertNotNull($user['verify_code_expires_at']);
        $this->assertNotNull($user['created_at']);
        $this->assertNotNull($user['updated_at']);

        // Seul le hash est stocké : le code du lien n'est pas en base
        $this->assertNotSame($user['verify_code'], $this->getLinkCode());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testRegisterWithAnAlreadyUsedMailGivesTheSameAnswer(): void
    {
        $first  = $this->register('duplicate@example.com');
        $second = $this->register('duplicate@example.com');

        $this->assertSame($first, $second);
        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM user WHERE mail = ?', ['duplicate@example.com']));

        // Seul le titulaire du mail apprend qu'il a déjà un compte
        $this->assertEmailCount(1);
        $this->assertStringContainsString('/login', $this->getMailerMessage()->getHtmlBody());
        $this->assertStringNotContainsString('/user/verify-mail/', $this->getMailerMessage()->getHtmlBody());
    }

    public function testRegisterRefusesInvalidFields(): void
    {
        $this->assertFalse($this->register('not-a-mail')['success']);
        $this->assertFalse($this->register(str_repeat('a', 250) . '@example.com')['success']);
    }

    public function testRegisterRefusesWeakPasswords(): void
    {
        foreach (['MotDePa-26', 'motdepasse-2026', 'MOTDEPASSE-2026', 'MotDePasse-abcd', 'MotDePasse2026', 'MotDePasse 2026'] as $password) {
            $this->assertFalse($this->register('weak@example.com', $password)['success'], $password);
        }

        $this->assertTrue($this->register('strong@example.com', 'Émeraude#Ciel4')['success']);
    }

    public function testRegisterRefusesPasswordsContainingPersonalInformation(): void
    {
        // Nom, prénom (sans tenir compte des accents ni de la casse), pseudo, début du mail
        foreach (['Secret-1-NOM', 'PRENOM-secret-1', 'Pseudo-Secret-1', 'Julie.Martin-1', 'Secret-Martin-1'] as $password) {
            $this->assertFalse($this->register('julie.martin@example.com', $password)['success'], $password);
        }

        // Le domaine du mail n'est pas une donnée personnelle
        $this->assertTrue($this->register('julie.martin@example.com', 'Example-Secret-1')['success']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testMailIsWrittenInTheRecipientLanguage(): void
    {
        $token = $this->client->request('GET', '/login')->filter('#localeSwitcherMenu input[name="_token"]')->attr('value');
        $this->client->request('POST', '/locale/switch', ['_token' => $token, 'code' => 'en', '_target_path' => '/login']);
        $this->register('english@example.com');

        $email = $this->getMailerMessage();

        $this->assertSame('no-reply@gta5-online.fr', $email->getFrom()[0]->getAddress());
        $this->assertSame('Validate your GTA 5 Online account', $email->getSubject());
    }

    /**
     * Un en-tête Host forgé ne doit pas envoyer un lien valide vers un autre site
     */
    public function testMailLinksAlwaysUseTheSiteUrl(): void
    {
        $this->client->setServerParameter('HTTP_HOST', 'evil.example');
        $this->register('host@example.com');

        $html = $this->getMailerMessage()->getHtmlBody();

        $this->assertStringContainsString('href="' . rtrim($_ENV['URL_SITE'], '/') . '/user/verify-mail/', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testWrongPasswordDoesNotRevealAnUnverifiedAccount(): void
    {
        $this->register('hidden@example.com');

        $this->submitLogin('hidden@example.com', 'mauvaismotdepasse');
        $hidden = $this->client->followRedirect()->filter('.alert-danger')->text();

        $this->submitLogin('nobody@example.com', 'mauvaismotdepasse');

        $this->assertSame($this->client->followRedirect()->filter('.alert-danger')->text(), $hidden);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testUnverifiedAccountCannotLogIn(): void
    {
        $this->register('unverified@example.com');

        $this->submitLogin('unverified@example.com', self::PASSWORD);
        $this->client->followRedirect();

        $this->assertSelectorExists('.alert-danger');
        $this->client->request('GET', '/');
        $this->assertResponseRedirects('/login');
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testValidLinkVerifiesTheMailAndLogsIn(): void
    {
        $this->register('verify@example.com');

        $userId   = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['verify@example.com']);
        $response = $this->post('/user/verify-mail', ['userId' => $userId, 'code' => $this->getLinkCode()], '/login');

        $this->assertTrue($response['success']);
        $this->assertSame(1, (int)$this->fetchOne('SELECT verify_mail FROM user WHERE id = ?', [$userId]));
        $this->assertSame('', $this->fetchOne('SELECT verify_code FROM user WHERE id = ?', [$userId]));

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testThreeWrongCodesInvalidateTheLink(): void
    {
        $this->register('attempts@example.com');

        $userId = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['attempts@example.com']);
        $code   = $this->getLinkCode();

        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse($this->post('/user/verify-mail', ['userId' => $userId, 'code' => str_repeat('0', 64)], '/login')['success']);
        }

        $response = $this->post('/user/verify-mail', ['userId' => $userId, 'code' => $code], '/login');

        $this->assertFalse($response['success']);
        $this->assertTrue($response['resend']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT verify_mail FROM user WHERE id = ?', [$userId]));

        $this->post('/user/verify-mail/resend', ['userId' => $userId], '/login');
        $this->assertTrue($this->post('/user/verify-mail', ['userId' => $userId, 'code' => $this->getLinkCode()], '/login')['success']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testExpiredLinkIsRefused(): void
    {
        $this->register('expired@example.com');

        $userId = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['expired@example.com']);
        $this->connection->executeStatement('UPDATE user SET verify_code_expires_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $userId]);

        $this->assertFalse($this->post('/user/verify-mail', ['userId' => $userId, 'code' => $this->getLinkCode()], '/login')['success']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testForgotPasswordGivesTheSameAnswerForAnUnknownMail(): void
    {
        $unknown = $this->post('/user/forgot-password', ['mail' => 'unknown@example.com'], '/login');

        $this->assertTrue($unknown['success']);
        $this->assertEmailCount(0);

        $this->register('forgot@example.com');
        $known = $this->post('/user/forgot-password', ['mail' => 'forgot@example.com'], '/login');

        $this->assertSame($unknown['message'], $known['message']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testResetPasswordChangesThePasswordVerifiesTheMailAndLogsIn(): void
    {
        $this->register('reset@example.com');
        $this->post('/user/forgot-password', ['mail' => 'reset@example.com'], '/login');

        $userId   = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['reset@example.com']);
        $password = $this->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]);
        $code     = $this->getLinkCode();

        $mismatch = $this->post(
            '/user/reset-password', ['userId' => $userId, 'code' => $code, 'password' => 'NouveauMotDePasse-2026', 'passwordConfirmation' => 'autre'], '/login'
        );
        $this->assertFalse($mismatch['success']);

        // Un mot de passe refusé avec un code valide laisse le lien utilisable
        $personal = $this->post(
            '/user/reset-password', ['userId' => $userId, 'code' => $code, 'password' => 'Pseudo-Secret-1', 'passwordConfirmation' => 'Pseudo-Secret-1'], '/login'
        );
        $this->assertFalse($personal['success']);

        $response = $this->post(
            '/user/reset-password', ['userId' => $userId, 'code' => $code, 'password' => 'NouveauMotDePasse-2026', 'passwordConfirmation' => 'NouveauMotDePasse-2026'], '/login'
        );

        $this->assertTrue($response['success']);
        $this->assertEmailCount(1);
        $this->assertStringContainsString('/login', $this->getMailerMessage()->getHtmlBody());
        $this->assertNotSame($password, $this->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]));
        $this->assertSame(1, (int)$this->fetchOne('SELECT verify_mail FROM user WHERE id = ?', [$userId]));

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    /**
     * Sans code valide, la réponse ne dit pas si le mot de passe contient les données personnelles du compte
     *
     * @throws DoctrineDBALException
     */
    public function testResetPasswordWithAnInvalidCodeDoesNotRevealPersonalInformation(): void
    {
        $this->register('reveal@example.com');
        $this->post('/user/forgot-password', ['mail' => 'reveal@example.com'], '/login');

        $userId = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['reveal@example.com']);
        $wrong  = str_repeat('0', 64);

        $personal = $this->post('/user/reset-password', ['userId' => $userId, 'code' => $wrong, 'password' => 'Pseudo-Secret-1', 'passwordConfirmation' => 'Pseudo-Secret-1'], '/login');
        $other    = $this->post('/user/reset-password', ['userId' => $userId, 'code' => $wrong, 'password' => 'Autre-Secret-1', 'passwordConfirmation' => 'Autre-Secret-1'], '/login');

        $this->assertFalse($personal['success']);
        $this->assertSame($other['message'], $personal['message']);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testAdminFormRefusesWeakPasswordsAndNotifiesAChange(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $fields = [
            'lastname' => 'Nom', 'firstname' => 'Prénom', 'mail' => 'admin-password@example.com', 'password' => 'motdepasse', 'username' => 'Pseudo',
            'active'   => 1, 'groupGtaId' => 3, 'role' => 'ROLE_USER', 'userId' => ''
        ];

        $this->assertFalse($this->post('/user/form', $fields, '/users')['success']);
        $this->assertTrue($this->post('/user/form', [...$fields, 'password' => self::PASSWORD], '/users')['success']);

        $userId = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', ['admin-password@example.com']);
        $hash   = $this->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]);

        // Sans mot de passe, la modification garde l'ancien et ne prévient pas
        $this->assertTrue($this->post('/user/form', [...$fields, 'password' => '', 'userId' => $userId], '/users')['success']);
        $this->assertSame($hash, $this->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]));
        $this->assertEmailCount(0);

        $this->assertTrue($this->post('/user/form', [...$fields, 'password' => 'NouveauMotDePasse-2026', 'userId' => $userId], '/users')['success']);
        $this->assertNotSame($hash, $this->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]));
        $this->assertEmailCount(1);
        $this->assertStringContainsString('/login', $this->getMailerMessage()->getHtmlBody());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testAccountCreatedByAnAdminReceivesTheValidationMail(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $response = $this->post(
            '/user/form',
            [
                'lastname' => 'Nom', 'firstname' => 'Prénom', 'mail' => 'admin-created@example.com', 'password' => self::PASSWORD, 'username' => 'Pseudo',
                'active'   => 1, 'groupGtaId' => 3, 'role' => 'ROLE_USER', 'userId' => ''
            ],
            '/users'
        );

        $this->assertTrue($response['success']);
        $this->assertSame(0, (int)$this->fetchOne('SELECT verify_mail FROM user WHERE mail = ?', ['admin-created@example.com']));
        $this->assertStringContainsString('/user/verify-mail/', $this->getMailerMessage()->getHtmlBody());
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testDefaultGroupCanOnlyBeMoved(): void
    {
        $defaultId = (int)$this->fetchOne('SELECT id FROM group_gta WHERE `default` = 1');

        $this->loginAs(1, 'ROLE_ADMIN');

        $response = $this->post('/group-gta/form', ['groupGtaId' => $defaultId, 'name' => 'Groupe', 'description' => 'Description', 'default' => 0], '/group-gtas');
        $this->assertFalse($response['success']);
        $this->assertSame($defaultId, (int)$this->fetchOne('SELECT id FROM group_gta WHERE `default` = 1'));

        $otherId = $defaultId === 4 ? 3 : 4;
        $this->post('/group-gta/form', ['groupGtaId' => $otherId, 'name' => 'Groupe', 'description' => 'Description', 'default' => 1], '/group-gtas');
        $this->assertSame([$otherId], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM group_gta WHERE `default` = 1')));

        $this->connection->executeStatement('UPDATE group_gta SET `default` = (id = ?)', [$defaultId]);
    }

    public function testRegisterIsRefusedWithoutThePersonalDatasConsent(): void
    {
        $response = $this->post(
            '/user/register',
            ['lastname' => 'Nom', 'firstname' => 'Prénom', 'username' => 'Pseudo', 'mail' => 'no-consent@example.com', 'password' => self::PASSWORD],
            '/login'
        );

        $this->assertFalse($response['success']);
        $this->assertEmailCount(0);
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM user WHERE mail = ?', ['no-consent@example.com']));
    }

    public function testLegalMentionsArePublicAndAccountIsNot(): void
    {
        $this->client->request('GET', '/legal-mentions');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/user/account');
        $this->assertResponseRedirects();
    }

    public function testExportContainsTheDatasOfTheUser(): void
    {
        $this->registerAndLogIn('export@example.com');

        $this->client->request('GET', '/user/account/export');
        $this->assertResponseIsSuccessful();

        $datas = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('export@example.com', $datas['account']['mail']);
        $this->assertArrayNotHasKey('password', $datas['account']);
        $this->assertArrayHasKey('vehicle_user', $datas);
    }

    public function testAccountDeletionRequiresThePasswordThenAnonymizes(): void
    {
        $id = $this->registerAndLogIn('delete@example.com');

        $this->assertFalse($this->post('/user/account/delete', ['password' => 'wrong'], '/user/account')['success']);
        $this->assertSame('delete@example.com', $this->fetchOne('SELECT mail FROM user WHERE id = ?', [$id]));

        $this->assertTrue($this->post('/user/account/delete', ['password' => self::PASSWORD], '/user/account')['success']);

        $this->assertSame('user-' . $id . '@' . UserHelper::ANONYMIZED_DOMAIN, $this->fetchOne('SELECT mail FROM user WHERE id = ?', [$id]));
        $this->assertSame('anonymized', $this->fetchOne('SELECT lastname FROM user WHERE id = ?', [$id]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT active FROM user WHERE id = ?', [$id]));
    }

    public function testPurgeWarnsThenAnonymizesInactiveAccounts(): void
    {
        $this->register('inactive@example.com');
        $id = $this->fetchOne('SELECT id FROM user WHERE mail = ?', ['inactive@example.com']);

        $this->connection->executeStatement("UPDATE user SET verify_mail = 1, last_activity = '2000-01-01 00:00:00' WHERE id = ?", [$id]);

        $tester = new CommandTester(new Application(static::$kernel)->find('users:purge'));

        $tester->execute(['--dry-run' => true]);
        $this->assertNull($this->fetchOne('SELECT inactivity_warned_at FROM user WHERE id = ?', [$id]));

        $tester->execute([]);
        $this->assertNotNull($this->fetchOne('SELECT inactivity_warned_at FROM user WHERE id = ?', [$id]));
        $this->assertSame('Nom', $this->fetchOne('SELECT lastname FROM user WHERE id = ?', [$id]));

        $this->connection->executeStatement("UPDATE user SET inactivity_warned_at = '2000-01-01 00:00:00' WHERE id = ?", [$id]);
        $tester->execute([]);
        $this->assertSame('anonymized', $this->fetchOne('SELECT lastname FROM user WHERE id = ?', [$id]));
    }

    public function testPurgeAnonymizesAccountsNeverVerified(): void
    {
        $this->register('unverified@example.com');
        $id = $this->fetchOne('SELECT id FROM user WHERE mail = ?', ['unverified@example.com']);

        $this->connection->executeStatement("UPDATE user SET created_at = '2000-01-01 00:00:00' WHERE id = ?", [$id]);

        new CommandTester(new Application(static::$kernel)->find('users:purge'))->execute([]);

        $this->assertSame('anonymized', $this->fetchOne('SELECT lastname FROM user WHERE id = ?', [$id]));
    }

    private function registerAndLogIn(string $mail): int
    {
        $this->register($mail);

        $id            = (int)$this->fetchOne('SELECT id FROM user WHERE mail = ?', [$mail]);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entityManager->clear();
        $this->client->loginUser($entityManager->getRepository(User::class)->find($id));

        return $id;
    }

    private function register(string $mail, string $password = self::PASSWORD): array
    {
        return $this->post(
            '/user/register',
            ['lastname' => 'Nom', 'firstname' => 'Prénom', 'username' => 'Pseudo', 'mail' => $mail, 'password' => $password, 'personalDatas' => '1'],
            '/login'
        );
    }

    /**
     * Code du lien envoyé par la dernière requête
     */
    private function getLinkCode(): string
    {
        /** @var Email $email */
        $email = $this->getMailerMessage();

        preg_match('#/user/(?:verify-mail|reset-password)/\d+/([a-f0-9]{64})#', $email->getHtmlBody(), $matches);

        return $matches[1];
    }
}

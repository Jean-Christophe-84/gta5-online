<?php

namespace App\Tests\Browser;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Pilote Chromium (Panther) sur le serveur PHP intégré, en env test sur db_test
 */
abstract class BrowserTestCase extends PantherTestCase
{
    const string PASSWORD = 'Panther-Test-1!';

    const int USER_ID = 1;

    // Routes qui ne sont pas des pages : JSON, fichiers, liens à code, actions
    const string NOT_PAGE_ROUTES = '/^(check_command_.+|export_account_user|filter_.+|get_.+|logout|reset_password_user|verify_mail_user)$/';

    const array VIEWPORTS = ['desktop' => [1200, 1000], 'mobile' => [375, 800], 'tablet' => [800, 1000]];

    private static ?bool   $loggedIn = null;
    private static ?string $viewport = null;

    public static function pageProvider(): iterable
    {
        $kernel = static::createKernel();
        $kernel->boot();

        /** @var RouterInterface $router */
        $router = $kernel->getContainer()->get('router');

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (str_starts_with($name, '_') || $route->getMethods() === ['POST'] || preg_match(self::NOT_PAGE_ROUTES, $name)) {
                continue;
            }

            yield $name => [$route->getPath()];
        }

        $kernel->shutdown();
    }

    protected function assertNoJavascriptError(Client $client, string $label): void
    {
        $this->assertSame([], $this->getConsoleErrors($client), $label . ' : ' . $client->getCurrentURL());
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function getBrowserClient(bool $admin, string $viewport = 'desktop'): Client
    {
        $client = static::createPantherClient([], [], ['capabilities' => ['goog:loggingPrefs' => ['browser' => 'ALL']]]);

        if (self::$viewport !== $viewport) {
            $client->getWebDriver()->manage()->window()->setSize(new WebDriverDimension(...self::VIEWPORTS[$viewport]));
            self::$viewport = $viewport;
        }

        if (self::$loggedIn !== $admin) {
            $client->request('GET', '/legal-mentions');
            $client->getWebDriver()->manage()->deleteAllCookies();
            self::$loggedIn = false;
        }

        if ($admin && !self::$loggedIn) {
            $this->login($client);
            self::$loggedIn = true;
        }

        $this->getConsoleErrors($client);

        return $client;
    }

    /**
     * Erreurs graves de la console : exception JS, ressource ou appel AJAX en erreur
     */
    protected function getConsoleErrors(Client $client): array
    {
        $errors = [];

        foreach ($client->getWebDriver()->manage()->getLog('browser') as $log) {
            if ($log['level'] === 'SEVERE' && !str_contains($log['message'], 'challenges.cloudflare.com')) {
                $errors[] = $log['message'];
            }
        }

        return $errors;
    }

    protected function getConnection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    protected function visit(Client $client, string $path): void
    {
        $client->request('GET', $path);

        $this->waitForAjax($client);
    }

    protected function waitFor(Client $client, string $condition, int $milliseconds = 5000): bool
    {
        for ($i = 0; $i < $milliseconds / 100; $i++) {
            if ($client->executeScript('return !!(' . $condition . ');')) {
                return true;
            }

            usleep(100000);
        }

        return false;
    }

    /**
     * Compte les fetch en cours (installé à chaque page) jusqu'à la lecture complète de leur réponse et à ses traitements, et attend aussi la fin du loader du layout
     */
    protected function waitForAjax(Client $client, string $label = '', int $milliseconds = 15000): void
    {
        $client->executeScript(
            "if (!window.pantherFetch) {
                window.pantherFetch = window.fetch;
                window.pantherPending = 0;
                window.fetch = function () {
                    const done = function () { setTimeout(function () { window.pantherPending--; }); };

                    window.pantherPending++;

                    return window.pantherFetch.apply(this, arguments).then(function (response) {
                        response.clone().text().finally(done);

                        return response;
                    }, function (error) {
                        done();

                        throw error;
                    });
                };
            }"
        );

        $idle = "document.readyState === 'complete' && window.pantherPending === 0
                 && !document.getElementById('page-loader')?.classList.contains('is-visible')";

        if (!$this->waitFor($client, $idle, $milliseconds)) {
            $this->fail(
                'Appels AJAX toujours en cours : ' . $label . ' ' . $client->getCurrentURL() . ' ' . json_encode($client->executeScript(
                    "return {pending: window.pantherPending, loader: document.getElementById('page-loader')?.classList.contains('is-visible')};"
                )) . "\n" . implode("\n", $this->getConsoleErrors($client))
            );
        }
    }

    /**
     * Connexion par le vrai formulaire (Turnstile en clé de test) de l'utilisateur 1, administrateur du groupe 1
     *
     * @throws DoctrineDBALException
     */
    private function login(Client $client): void
    {
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user           = static::getContainer()->get('doctrine')->getRepository(User::class)->find(self::USER_ID);

        $this->getConnection()->executeStatement(
            'UPDATE user SET active = 1, group_gta_id = 1, password = :password, roles = :roles, verify_mail = 1 WHERE id = :id',
            ['id' => self::USER_ID, 'password' => $passwordHasher->hashPassword($user, self::PASSWORD), 'roles' => json_encode(['ROLE_ADMIN'])]
        );

        $client->request('GET', '/login');

        if (!$this->waitFor($client, "document.querySelector('form.validation_login [name=\"cf-turnstile-response\"]')?.value", 15000)) {
            $this->fail('Le jeton Turnstile de test n\'a pas été généré');
        }

        $client->executeScript(
            "document.querySelector('form.validation_login #username').value = " . json_encode($user->getMail()) . ";
             document.querySelector('form.validation_login [name=\"_password\"]').value = " . json_encode(self::PASSWORD) . ";
             document.querySelector('form.validation_login').submit();"
        );

        try {
            $client->wait(10)->until(WebDriverExpectedCondition::not(WebDriverExpectedCondition::urlContains('/login')));
        } catch (TimeoutException) {
            $this->fail('Connexion de l\'administrateur impossible');
        }
    }
}

<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class LocaleTest extends AppWebTestCase
{
    public function testAnonymousSwitchesTheLanguageOfThePages(): void
    {
        $this->assertSame('fr', $this->client->request('GET', '/login')->filter('html')->attr('lang'));

        $this->switchLocale('en', '/login');

        $this->assertResponseRedirects('/login');
        $this->assertSame('en', $this->client->request('GET', '/login')->filter('html')->attr('lang'));
        $this->assertSame('en', $this->client->request('GET', '/legal-mentions')->filter('html')->attr('lang'));
    }

    public function testExternalTargetPathRedirectsToTheHomepage(): void
    {
        foreach (['https://example.com/', '//example.com/', '/\\example.com/'] as $targetPath) {
            $this->switchLocale('en', $targetPath);

            $this->assertResponseRedirects('/', null, $targetPath . ' ne doit pas rediriger hors du site');
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testLoggedInUserKeepsTheChosenLanguage(): void
    {
        $this->connection->executeStatement('UPDATE user SET locale_id = (SELECT id FROM locale WHERE code = "fr") WHERE id = ?', [self::USER_ID]);
        $this->loginAs(1, 'ROLE_ADMIN');

        try {
            $this->switchLocale('en', '/vehicles', '/vehicles');

            $this->assertSame('en', $this->fetchOne('SELECT locale.code FROM user INNER JOIN locale ON locale.id = user.locale_id WHERE user.id = ?', [self::USER_ID]));
            $this->assertSame('en', $this->client->request('GET', '/vehicles')->filter('html')->attr('lang'));
        } finally {
            $this->connection->executeStatement('UPDATE user SET locale_id = (SELECT id FROM locale WHERE code = "fr") WHERE id = ?', [self::USER_ID]);
        }
    }

    public function testSwitchNeedsAValidTokenAndAKnownLanguage(): void
    {
        $this->client->request('POST', '/locale/switch', ['_target_path' => '/login', '_token' => 'invalide', 'code' => 'en']);

        $this->assertSame('fr', $this->client->request('GET', '/login')->filter('html')->attr('lang'));

        $this->switchLocale('xx', '/login');

        $this->assertSame('fr', $this->client->request('GET', '/login')->filter('html')->attr('lang'));
    }

    private function switchLocale(string $code, string $targetPath, string $page = '/login'): void
    {
        $token = $this->client->request('GET', $page)->filter('#localeSwitcherMenu input[name="_token"]')->attr('value');

        $this->client->request('POST', '/locale/switch', ['_target_path' => $targetPath, '_token' => $token, 'code' => $code]);
    }
}

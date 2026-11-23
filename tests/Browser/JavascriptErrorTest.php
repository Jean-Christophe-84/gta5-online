<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Chaque page HTML s'affiche sans erreur JavaScript, en visiteur et en administrateur
 */
#[Group('js')]
class JavascriptErrorTest extends BrowserTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('pageProvider')]
    public function testAdmin(string $path): void
    {
        $client = $this->getBrowserClient(true);

        $this->visit($client, $path);

        $this->assertNotSame('/login', parse_url($client->getCurrentURL(), PHP_URL_PATH), $path . ' : l\'administrateur doit rester connecté');
        $this->assertNoJavascriptError($client, $path);
    }

    /**
     * Garde-fou : une erreur JavaScript doit bien être détectée
     *
     * @throws DoctrineDBALException
     */
    public function testErrorsAreDetected(): void
    {
        $client = $this->getBrowserClient(false);

        $this->visit($client, '/login');
        $client->executeScript("setTimeout(function () { undefinedFunctionForPanther(); }, 0);");
        usleep(300000);

        $this->assertNotEmpty($this->getConsoleErrors($client));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('pageProvider')]
    public function testAnonymous(string $path): void
    {
        $client = $this->getBrowserClient(false);

        $this->visit($client, $path);

        $this->assertNoJavascriptError($client, $path);
    }
}

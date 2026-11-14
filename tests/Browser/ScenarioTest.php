<?php

namespace App\Tests\Browser;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;

/**
 * Parcours réellement enregistrés, avec de vrais clics et de vraies frappes, à chaque taille d'écran
 */
#[Group('js')]
class ScenarioTest extends BrowserTestCase
{
    public static function viewportProvider(): iterable
    {
        foreach (array_keys(self::VIEWPORTS) as $viewport) {
            yield $viewport => [$viewport];
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('viewportProvider')]
    public function testHeistChoiceIsSavedWithTomSelect(string $viewport): void
    {
        $this->getConnection()->executeStatement('UPDATE heist_casino SET difficulty = NULL WHERE user_id = ?', [self::USER_ID]);

        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, '/heist/casino');
        $this->chooseInTomSelect($client, 'difficulty', 'hard');
        $this->waitForAjax($client, 'difficulté du Casino');

        $this->assertNoJavascriptError($client, 'braquage du Casino');
        $this->assertSame('hard', $this->getConnection()->fetchOne('SELECT difficulty FROM heist_casino WHERE user_id = ?', [self::USER_ID]));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('viewportProvider')]
    public function testHeistCayoPericoAndKortzCenterChoicesAreSaved(string $viewport): void
    {
        $this->getConnection()->executeStatement('UPDATE heist_cayo_perico SET difficulty = NULL, gold_main_west = 0 WHERE user_id = ?', [self::USER_ID]);
        $this->getConnection()->executeStatement('UPDATE heist_kortz_center SET material = NULL WHERE user_id = ?', [self::USER_ID]);

        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, '/heist/cayo-perico');
        $this->chooseInTomSelect($client, 'difficulty', 'hard');
        $this->waitForAjax($client, 'difficulté de Cayo Perico');
        $this->typeAndLeave($client, '#gold_main_west', '2');
        $this->waitForAjax($client, 'or de Cayo Perico');

        $this->assertNoJavascriptError($client, 'braquage de Cayo Perico');
        $this->assertSame(
            ['difficulty' => 'hard', 'gold_main_west' => 2],
            $this->getConnection()->fetchAssociative('SELECT difficulty, gold_main_west FROM heist_cayo_perico WHERE user_id = ?', [self::USER_ID])
            |> (fn(array $row) => ['difficulty' => $row['difficulty'], 'gold_main_west' => (int)$row['gold_main_west']])
        );

        $this->visit($client, '/heist/kortz-center');
        $this->chooseInTomSelect($client, 'material', 'safety-suit');
        $this->waitForAjax($client, 'matériel du Kortz Center');

        $secondaryLootId = (int)$client->executeScript(
            "return document.querySelector('.form_secondary_loot_kortz_center_heist[data-type=\"price\"]').getAttribute('data-id');"
        );

        $this->typeAndLeave($client, '.form_secondary_loot_kortz_center_heist[data-type="price"][data-id="' . $secondaryLootId . '"]', '73500');
        $this->waitForAjax($client, 'prix d\'un butin secondaire');

        $this->assertNoJavascriptError($client, 'braquage du Kortz Center');
        $this->assertSame('safety-suit', $this->getConnection()->fetchOne('SELECT material FROM heist_kortz_center WHERE user_id = ?', [self::USER_ID]));
        $this->assertSame(73500, (int)$this->getConnection()->fetchOne(
            'SELECT heist_kortz_center_heist_kortz_center_secondary_loot.price
            FROM heist_kortz_center_heist_kortz_center_secondary_loot
            INNER JOIN heist_kortz_center_secondary_loot_name
                ON heist_kortz_center_secondary_loot_name.id = heist_kortz_center_heist_kortz_center_secondary_loot.heist_kortz_center_secondary_loot_name_id
            WHERE heist_kortz_center_heist_kortz_center_secondary_loot.user_id = ? AND heist_kortz_center_secondary_loot_name.heist_kortz_center_secondary_loot_id = ?',
            [self::USER_ID, $secondaryLootId]
        ));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('viewportProvider')]
    public function testOwnershipIsSavedByTheCheckbox(string $viewport): void
    {
        $weaponId = (int)$this->getConnection()->fetchOne(
            'SELECT MIN(id) FROM weapon WHERE evolution = 0 AND id NOT IN (SELECT weapon_two_id FROM weapon_weapon) AND id NOT IN (SELECT weapon_one_id FROM weapon_weapon)'
        );

        $this->getConnection()->executeStatement('UPDATE weapon_user SET owned = 0 WHERE user_id = ? AND weapon_id = ?', [self::USER_ID, $weaponId]);

        $name   = $this->getConnection()->fetchOne('SELECT name FROM weapon_locale WHERE weapon_id = ? AND locale_id = 1', [$weaponId]);
        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, '/weapon/filter?owned=null&page=1&search=' . rawurlencode($name));
        $this->visit($client, '/weapons');
        $client->executeScript("document.getElementById('weaponOwned_$weaponId').scrollIntoView({block: 'center'});");
        $client->getWebDriver()->findElement(WebDriverBy::id('weaponOwned_' . $weaponId))->click();
        $this->waitForAjax($client, 'case possédée');

        $this->assertNoJavascriptError($client, 'case possédée');
        $this->assertSame(1, (int)$this->getConnection()->fetchOne(
            'SELECT owned FROM weapon_user WHERE user_id = ? AND weapon_id = ?', [self::USER_ID, $weaponId]
        ));
        $this->assertTrue((bool)$client->executeScript("return document.getElementById('weaponOwned_$weaponId').checked;"));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('viewportProvider')]
    public function testReferenceIsAddedByTheModal(string $viewport): void
    {
        $name = 'Personnalisation Panther ' . $viewport;

        $this->getConnection()->executeStatement('DELETE FROM customization_locale WHERE name = ?', [$name]);

        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, '/customizations');
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#addCustomization button'))->click();
        $this->waitForAjax($client, 'modale d\'ajout');

        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#customizationModal input[name="name"]'))->sendKeys($name);
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#customizationModal .modal-footer .btn-success'))->click();
        $this->waitForAjax($client, 'enregistrement');

        $this->assertNoJavascriptError($client, 'ajout d\'une personnalisation');
        $this->assertTrue((bool)$client->executeScript("return document.getElementById('alert-success').classList.contains('show');"));
        $this->assertFalse((bool)$client->executeScript("return document.getElementById('customizationModal').classList.contains('is-open');"));
        $this->assertSame(1, (int)$this->getConnection()->fetchOne('SELECT COUNT(*) FROM customization_locale WHERE name = ?', [$name]));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('viewportProvider')]
    public function testReferenceWithoutNameShowsTheError(string $viewport): void
    {
        $count  = (int)$this->getConnection()->fetchOne('SELECT COUNT(*) FROM customization');
        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, '/customizations');
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#addCustomization button'))->click();
        $this->waitForAjax($client, 'modale d\'ajout');
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#customizationModal .modal-footer .btn-success'))->click();
        $this->waitForAjax($client, 'enregistrement refusé');

        $this->assertTrue((bool)$client->executeScript("return document.querySelector('#customizationModal #error_name').classList.contains('show');"));
        $this->assertTrue((bool)$client->executeScript("return document.getElementById('customizationModal').classList.contains('is-open');"));
        $this->assertSame($count, (int)$this->getConnection()->fetchOne('SELECT COUNT(*) FROM customization'));

        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#customizationModal .modal-footer [data-modal-close]'))->click();
    }

    /**
     * Frappe réelle puis sortie du champ (Tab), ce qui déclenche l'événement change
     */
    private function typeAndLeave(Client $client, string $selector, string $value): void
    {
        $client->executeScript("document.querySelector(" . json_encode($selector) . ").scrollIntoView({block: 'center'});");

        $input = $client->getWebDriver()->findElement(WebDriverBy::cssSelector($selector));
        $input->clear();
        $input->sendKeys($value . WebDriverKeys::TAB);
    }

    private function chooseInTomSelect(Client $client, string $selectId, string $value): void
    {
        $client->executeScript("document.getElementById('$selectId').tomselect.wrapper.scrollIntoView({block: 'center'});");
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#' . $selectId . ' + .ts-control .items'))->click();

        $this->assertTrue($this->waitFor($client, "document.querySelector('#$selectId-ts-dropdown [data-value=\"$value\"]')"), $selectId . ' : option ' . $value);

        $client->executeScript(
            "const option = document.querySelector('#$selectId-ts-dropdown [data-value=\"$value\"]');
             const content = option.closest('.ts-dropdown-content');

             content.scrollTop = option.offsetTop - content.offsetTop;"
        );
        usleep(200000);
        $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#' . $selectId . '-ts-dropdown [data-value="' . $value . '"]'))->click();
    }
}

<?php

namespace App\Tests\Browser;

use App\Tests\Functional\ListFilterTest;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;

/**
 * Sur chaque liste et à chaque taille d'écran : recherche, filtres, tris, retrait des filtres, ouverture et fermeture de chaque modale
 * (ajout, édition, copie, suppression), sans rien enregistrer
 */
#[Group('js')]
class PageInteractionTest extends BrowserTestCase
{
    // Premier bouton de chaque sorte de modale : ajout (hors tableau), édition, copie, suppression
    const array MODAL_OPENERS = [
        'ajout'       => ':not(table) > [data-modal-open]:not([data-delete])',
        'copie'       => 'table [data-modal-open][id^="copy"], table [data-modal-open][data-vehicle-copy-id], table [data-modal-open][data-weapon-copy-id]',
        'édition'     => 'table [data-modal-open][id^="form"]',
        'suppression' => 'table [data-modal-open][data-delete]'
    ];

    public static function listProvider(): iterable
    {
        foreach (array_keys(self::VIEWPORTS) as $viewport) {
            foreach (array_keys(ListFilterTest::LISTS) as $page) {
                yield $page . ' ' . $viewport => [$page, $viewport];
            }
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('listProvider')]
    public function testListInteractions(string $page, string $viewport): void
    {
        $client = $this->getBrowserClient(true, $viewport);

        $this->visit($client, $page);
        $this->assertNoJavascriptError($client, $page . ' affichage');
        $this->assertPaginationFits($client, $page);

        $this->changeFilters($client, $page);
        $this->removeAllFilters($client, $page);
        $this->search($client, $page);
        $this->clickSortHeaders($client, $page);
        $this->removeAllFilters($client, $page);
        $this->openAndCloseModals($client, $page);
    }

    private function assertPaginationFits(Client $client, string $page): void
    {
        $problems = $client->executeScript(
            "const bar = document.querySelector('div.pagination');

            if (!bar) {
                return [];
            }

            const nav = bar.querySelector('nav').getBoundingClientRect();
            const summary = Array.from(bar.querySelectorAll('span.pagination-summary, span.pagination-summary-short')).find(span => span.offsetWidth);

            if (!summary) {
                return ['pas de résumé'];
            }

            const text = summary.getBoundingClientRect();
            const problems = [];

            if (text.right > nav.left) {
                problems.push('le résumé passe sous les pages');
            }

            if (nav.right > document.documentElement.clientWidth || text.left < 0) {
                problems.push('hors de l\\'écran');
            }

            if (summary.scrollWidth > summary.clientWidth) {
                problems.push('résumé coupé');
            }

            return problems;"
        );

        $this->assertSame([], $problems, $page . ' pagination');
    }

    private function assertNoErrorShown(Client $client, string $label): void
    {
        $this->assertNoJavascriptError($client, $label);
        $this->assertFalse(
            (bool)$client->executeScript("return document.getElementById('alert-danger')?.classList.contains('show');"),
            $label . ' : ' . $client->executeScript("return document.getElementById('alert-danger')?.textContent.trim();")
        );
    }

    private function changeFilters(Client $client, string $page): void
    {
        $count = (int)$client->executeScript("return document.querySelectorAll('.filters select[id^=\"filter\"]:not([multiple])').length;");

        for ($i = 0; $i < $count; $i++) {
            $id = $client->executeScript(
                "const select = document.querySelectorAll('.filters select[id^=\"filter\"]:not([multiple])')[$i];

                if (!select || select.options.length < 2) {
                    return null;
                }

                select.value = select.options[select.options.length - 1].value;
                select.dispatchEvent(new Event('change', {bubbles: true}));

                return select.id;"
            );

            if ($id === null) {
                continue;
            }

            usleep(200000);
            $this->waitForAjax($client, $page . ' filtre ' . $id);
            $this->assertNoErrorShown($client, $page . ' filtre ' . $id);
        }
    }

    private function clickSortHeaders(Client $client, string $page): void
    {
        $sortKeys = $client->executeScript(
            "return Array.from(document.querySelectorAll('th[data-sort-key][onclick]')).map(function (header) { return header.getAttribute('data-sort-key'); });"
        );

        foreach (array_unique($sortKeys) as $sortKey) {
            $client->executeScript("document.querySelector('th[data-sort-key=\"$sortKey\"][onclick]').click();");
            usleep(200000);
            $this->waitForAjax($client, $page . ' tri ' . $sortKey);
            $this->assertNoErrorShown($client, $page . ' tri ' . $sortKey);
        }
    }

    private function openAndCloseModals(Client $client, string $page): void
    {
        foreach (self::MODAL_OPENERS as $type => $selector) {
            $modal = $client->executeScript(
                "const opener = document.querySelector(" . json_encode($selector) . ");

                if (!opener) {
                    return null;
                }

                opener.click();

                return opener.getAttribute('data-modal-open');"
            );

            if ($modal === null) {
                continue;
            }

            $label = $page . ' modale ' . $type;

            usleep(200000);
            $this->waitForAjax($client, $label);

            $this->assertNoErrorShown($client, $label);
            $this->assertTrue((bool)$client->executeScript("return document.querySelector('$modal').classList.contains('is-open');"), $label . ' ouverte');
            $this->assertNotSame('', trim((string)$client->executeScript("return document.querySelector('$modal .modal-body')?.innerHTML ?? 'x';")), $label . ' remplie');

            $client->executeScript("document.querySelector('$modal [data-modal-close]').click();");

            $this->assertTrue($this->waitFor($client, "!document.querySelector('$modal').classList.contains('is-open')"), $label . ' fermée');
        }
    }

    private function removeAllFilters(Client $client, string $page): void
    {
        if (!$client->executeScript("const button = document.getElementById('removeAllFilters'); if (button) { button.click(); } return !!button;")) {
            return;
        }

        usleep(200000);
        $this->waitForAjax($client, $page . ' retrait des filtres');
        $this->assertNoErrorShown($client, $page . ' retrait des filtres');
        $this->assertSame('', $client->executeScript("return document.getElementById('filterSearch')?.value ?? '';"), $page . ' recherche vidée');
    }

    private function search(Client $client, string $page): void
    {
        if (!$client->executeScript(
            "const input = document.getElementById('filterSearch');

            if (input) {
                input.value = 'a';
                input.dispatchEvent(new Event('input', {bubbles: true}));
            }

            return !!input;"
        )) {
            return;
        }

        usleep(800000);
        $this->waitForAjax($client, $page . ' recherche');
        $this->assertNoErrorShown($client, $page . ' recherche');
    }
}

<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cycle complet des listes de référence : champ obligatoire, ajout, recherche, modale d'édition, modification
 */
class ReferenceFormTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public static function referenceProvider(): iterable
    {
        yield 'business' => ['business', 'businesses', 'business', true, ['maximumNumber' => 1, 'propertyTypeId' => 1, 'titleUpdateId' => 1]];
        yield 'customization' => ['customization', 'customizations', 'customization', true, []];
        yield 'delivery_method' => ['delivery-method', 'delivery-methods', 'delivery_method', true, []];
        yield 'group_gta' => ['group-gta', 'group-gtas', 'group_gta', true, ['default' => 0, 'description' => 'Description du test']];
        yield 'modification_location' => ['modification-location', 'modification-locations', 'modification_location', true, []];
        yield 'property_acquisition' => ['property-acquisition', 'property-acquisitions', 'property_acquisition', true, ['promotion' => 0]];
        yield 'property_type' => ['property-type', 'property-types', 'property_type', true, []];
        yield 'storage_location' => ['storage-location', 'storage-locations', 'storage_location', true, ['propertyRequired' => 0]];
        yield 'title_update' => ['title-update', 'title-updates', 'title_update', false, ['releaseDate' => '2026-01-01']];
        yield 'vehicle_acquisition' => [
            'vehicle-acquisition', 'vehicle-acquisitions', 'vehicle_acquisition', true, ['acquirable' => 1, 'available' => 1, 'promotion' => 0]
        ];
        yield 'vehicle_manufacturer' => ['vehicle-manufacturer', 'vehicle-manufacturers', 'vehicle_manufacturer', false, ['realName' => '']];
        yield 'weapon_acquisition' => ['weapon-acquisition', 'weapon-acquisitions', 'weapon_acquisition', true, ['promotion' => 0]];
        yield 'weapon_manufacturer' => ['weapon-manufacturer', 'weapon-manufacturers', 'weapon_manufacturer', false, []];
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('referenceProvider')]
    public function testReferenceCanBeAddedAndEdited(string $slug, string $page, string $table, bool $locale, array $parameters): void
    {
        $idParameter = lcfirst(str_replace('_', '', ucwords($table, '_'))) . 'Id';
        $name        = 'Référence de test ' . $table;
        $newName     = 'Référence modifiée ' . $table;
        $count       = (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');

        $this->loginAs(1, 'ROLE_ADMIN');

        $response = $this->post('/' . $slug . '/form', [$idParameter => '', 'name' => ''] + $parameters, '/' . $page);

        $this->assertFalse($response['success'], $slug . ' : le nom est obligatoire');
        $this->assertSame($count, (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`'));

        $response = $this->post('/' . $slug . '/form', [$idParameter => '', 'name' => $name] + $parameters, '/' . $page);

        $this->assertTrue($response['success'], $slug . ' : ' . ($response['message'] ?? ''));
        $this->assertSame($count + 1, (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`'));

        $id = $this->getIdByName($table, $locale, $name);

        $this->assertNotFalse($id, $slug . ' : élément ajouté introuvable');

        $this->client->request('GET', '/' . $slug . '/get', [$idParameter => $id]);
        $modal = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertStringContainsString($name, $modal['template'], $slug . ' : la modale d\'édition doit être remplie');

        $response = $this->post('/' . $slug . '/form', [$idParameter => $id, 'name' => $newName] + $parameters, '/' . $page);

        $this->assertTrue($response['success'], $slug . ' : ' . ($response['message'] ?? ''));
        $this->assertSame($count + 1, (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`'), $slug . ' : la modification ne doit rien ajouter');
        $this->assertSame((int)$id, (int)$this->getIdByName($table, $locale, $newName));
        $this->assertFalse($this->getIdByName($table, $locale, $name));
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('referenceProvider')]
    public function testReferenceCanBeSearched(string $slug, string $page, string $table, bool $locale, array $parameters): void
    {
        $name = $locale
            ? $this->fetchOne('SELECT name FROM ' . $table . '_locale WHERE locale_id = 1 ORDER BY ' . $table . '_id LIMIT 1')
            : $this->fetchOne('SELECT name FROM `' . $table . '` ORDER BY id LIMIT 1');

        $this->loginAs(1, 'ROLE_ADMIN');

        foreach ([$name, explode(' ', $name)[0]] as $search) {
            $this->client->request('GET', '/' . $slug . '/filter', ['search' => $search]);

            $this->assertResponseIsSuccessful($slug . ' : la recherche "' . $search . '" ne doit pas échouer');

            $filter = json_decode($this->client->getResponse()->getContent(), true);

            $this->assertTrue($filter['success']);
            $this->assertStringContainsString(htmlspecialchars($name), $filter['template'], $slug . ' : la recherche "' . $search . '" doit trouver "' . $name . '"');
        }

        $this->client->request('GET', '/' . $slug . '/filter', ['search' => 'zzzzzzzzzz']);

        $this->assertStringNotContainsString(htmlspecialchars($name), json_decode($this->client->getResponse()->getContent(), true)['template']);
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('referenceProvider')]
    public function testReferenceFormNeedsThePermission(string $slug, string $page, string $table, bool $locale, array $parameters): void
    {
        $idParameter = lcfirst(str_replace('_', '', ucwords($table, '_'))) . 'Id';
        $count       = (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');
        $groupGtaId  = (int)$this->fetchOne('SELECT MAX(id) FROM group_gta');

        $permissionIds = $this->connection->fetchFirstColumn(
            'SELECT permission_id FROM permission_group_gta INNER JOIN permission ON permission.id = permission_group_gta.permission_id
            WHERE group_gta_id = :groupGtaId AND permission_group_gta.active = 1 AND permission.attribute LIKE :attribute',
            ['attribute' => '%\\_ADD', 'groupGtaId' => $groupGtaId]
        );

        $this->connection->executeStatement(
            'UPDATE permission_group_gta SET active = 0 WHERE group_gta_id = :groupGtaId AND permission_id IN (:permissionIds)',
            ['groupGtaId' => $groupGtaId, 'permissionIds' => $permissionIds], ['permissionIds' => ArrayParameterType::INTEGER]
        );

        try {
            $this->loginAs($groupGtaId);

            $response = $this->post('/' . $slug . '/form', [$idParameter => '', 'name' => 'Ajout interdit'] + $parameters, '/');

            $this->assertFalse($response['success'] ?? false, $slug . ' : l\'ajout doit être refusé sans la permission');
            $this->assertSame($count, (int)$this->fetchOne('SELECT COUNT(*) FROM `' . $table . '`'));
        } finally {
            $this->connection->executeStatement(
                'UPDATE permission_group_gta SET active = 1 WHERE group_gta_id = :groupGtaId AND permission_id IN (:permissionIds)',
                ['groupGtaId' => $groupGtaId, 'permissionIds' => $permissionIds], ['permissionIds' => ArrayParameterType::INTEGER]
            );
        }
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getIdByName(string $table, bool $locale, string $name): mixed
    {
        if ($locale) {
            return $this->fetchOne('SELECT ' . $table . '_id FROM ' . $table . '_locale WHERE name = ?', [$name]);
        }

        return $this->fetchOne('SELECT id FROM `' . $table . '` WHERE name = ?', [$name]);
    }
}

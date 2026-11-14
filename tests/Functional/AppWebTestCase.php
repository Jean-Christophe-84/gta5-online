<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

abstract class AppWebTestCase extends WebTestCase
{
    const string TURNSTILE_RESPONSE = 'XXXX.DUMMY.TOKEN.XXXX';

    const int USER_ID = 1;

    protected KernelBrowser $client;
    protected Connection    $connection;

    protected function setUp(): void
    {
        $this->client     = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function fetchOne(string $query, array $parameters = []): mixed
    {
        return $this->connection->fetchOne($query, $parameters);
    }

    /**
     * Champs de la modale d'ajout ou d'édition, tels que le JS des pages les envoie (case = 0/1, liste multiple = tableau)
     */
    protected function getModalFields(string $uri, array $parameters = []): array
    {
        $this->client->request('GET', $uri, $parameters);

        $crawler = new Crawler(json_decode($this->client->getResponse()->getContent(), true)['template'] ?? '');
        $fields  = [];

        $crawler->filter('input[name], select[name], textarea[name]')->each(function (Crawler $node) use (&$fields) {
            $name = $node->attr('name');

            if (str_ends_with($name, '[]')) {
                $fields[substr($name, 0, -2)] = $node->filter('option[selected]')->each(fn(Crawler $option) => $option->attr('value'));
            } elseif ($node->nodeName() === 'select') {
                $fields[$name] = $node->filter('option[selected]')->count() ? $node->filter('option[selected]')->attr('value') : '';
            } elseif ($node->attr('type') === 'checkbox') {
                $fields[$name] = $node->attr('checked') !== null ? '1' : '0';
            } elseif ($node->nodeName() === 'textarea') {
                $fields[$name] = $node->text('');
            } else {
                $fields[$name] = (string)$node->attr('value');
            }
        });

        return $fields;
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function loginAs(int $groupGtaId, string $role = 'ROLE_USER'): void
    {
        $this->connection->executeStatement(
            'UPDATE user SET group_gta_id = :groupGtaId, roles = :roles WHERE id = :userId',
            ['groupGtaId' => $groupGtaId, 'roles' => json_encode([$role]), 'userId' => self::USER_ID]
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $this->client->loginUser($entityManager->getRepository(User::class)->find(self::USER_ID));
    }

    protected function post(string $uri, array $parameters, string $page): array
    {
        $token = $this->client->request('GET', $page)->filter('meta[name="csrf-token"]')->attr('content');

        $this->client->request('POST', $uri, $parameters, [], ['HTTP_X_CSRF_TOKEN' => $token]);

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function setOwned(string $table, string $column, array $owneds): void
    {
        foreach ($owneds as $id => $owned) {
            $this->connection->executeStatement(
                'UPDATE ' . $table . ' SET owned = :owned WHERE user_id = :userId AND ' . $column . ' = :id',
                ['id' => $id, 'owned' => (int)$owned, 'userId' => self::USER_ID]
            );
        }
    }

    /**
     * Le jeton factice est accepté par la clé secrète de test de Cloudflare (.env.test) ; null = formulaire envoyé sans Turnstile
     */
    protected function submitLogin(string $mail, string $password, ?string $turnstileResponse = self::TURNSTILE_RESPONSE): void
    {
        $form   = $this->client->request('GET', '/login')->filter('form.validation_login')->form(['_username' => $mail, '_password' => $password]);
        $values = $form->getPhpValues();

        if ($turnstileResponse !== null) {
            $values['cf-turnstile-response'] = $turnstileResponse;
        }

        $this->client->request($form->getMethod(), $form->getUri(), $values);
    }
}

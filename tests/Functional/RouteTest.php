<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\RouterInterface;

/**
 * Appelle toutes les routes de l'application : une route ajoutée est couverte automatiquement
 */
class RouteTest extends AppWebTestCase
{
    // Lancent un processus externe, déconnectent ou modifient le compte connecté : jamais appelées à vide
    const string EXCLUDED_POST_ROUTES = '/^(delete_account_user|launch_command_.+|logout|stop_command_.+|switch_locale)$/';

    const string MASKED_EXCEPTION = '/(à la ligne|at line) \d+ (du fichier|of file)/';

    // Accessibles sans être connecté
    const string PUBLIC_ROUTES = '/^(check_reset_password_user|check_verify_mail_user|forgot_password_user|legal_mentions|login|register_user|resend_verify_mail_user|reset_password_user|switch_locale|verify_mail_user)$/';

    public static function routeProvider(): iterable
    {
        $kernel = static::createKernel();
        $kernel->boot();

        /** @var RouterInterface $router */
        $router = $kernel->getContainer()->get('router');

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (!str_starts_with($name, '_')) {
                yield $name => [$name, $route->getMethods() === ['POST']];
            }
        }

        $kernel->shutdown();
    }

    #[DataProvider('routeProvider')]
    public function testAdminAjax(string $name, bool $postOnly): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        if ($postOnly) {
            $this->assertMethodNotAllowed($name);

            return;
        }

        $this->client->request('GET', $this->generateUrl($name), [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertNoServerError($name);
    }

    /**
     * @throws DoctrineDBALException
     */
    #[DataProvider('routeProvider')]
    public function testAdminGet(string $name, bool $postOnly): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        if ($postOnly) {
            $this->assertMethodNotAllowed($name);

            return;
        }

        $this->client->request('GET', $this->generateUrl($name));

        $this->assertNoServerError($name);

        if (str_starts_with($name, 'get_')) {
            foreach (['', '&delete=1'] as $delete) {
                $this->client->request('GET', $this->generateUrl($name) . '?' . $this->getIdParameter($name) . $delete);

                $this->assertNoServerError($name . ' (id' . $delete . ')');
            }
        }
    }

    /**
     * Un POST vide doit toujours répondre proprement (champ manquant, élément introuvable...)
     */
    #[DataProvider('routeProvider')]
    public function testAdminPostWithoutParameters(string $name, bool $postOnly): void
    {
        if (!$postOnly || preg_match(self::EXCLUDED_POST_ROUTES, $name)) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $this->loginAs(1, 'ROLE_ADMIN');

        $this->post($this->generateUrl($name), [], '/');

        $this->assertNoServerError($name);
    }

    #[DataProvider('routeProvider')]
    public function testAnonymous(string $name, bool $postOnly): void
    {
        $this->client->request($postOnly ? 'POST' : 'GET', $this->generateUrl($name));

        $this->assertNoServerError($name);

        if (!preg_match(self::PUBLIC_ROUTES, $name) && !$postOnly) {
            $this->assertResponseRedirects('/login', null, $name . ' doit être réservée aux utilisateurs connectés');
        }
    }

    private function assertMethodNotAllowed(string $name): void
    {
        $this->client->request('GET', $this->generateUrl($name));

        $this->assertResponseStatusCodeSame(405, $name . ' ne doit accepter que le POST');
    }

    private function assertNoServerError(string $name): void
    {
        $response = $this->client->getResponse();
        $content  = (string)$response->getContent();
        $json     = json_decode($content, true);

        if (is_array($json)) {
            $content = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->assertLessThan(500, $response->getStatusCode(), $name . ' : ' . mb_substr(strip_tags($content), 0, 500));

        // Une requête invalide (400) détaille volontairement l'erreur à l'administrateur : seul un succès apparent ne doit rien masquer
        if ($response->getStatusCode() >= 400) {
            return;
        }

        $this->assertSame(0, preg_match(self::MASKED_EXCEPTION, $content), $name . ' : exception masquée : ' . mb_substr($content, 0, 500));
    }

    private function generateUrl(string $name): string
    {
        /** @var RouterInterface $router */
        $router     = static::getContainer()->get('router');
        $parameters = [];

        foreach ($router->getRouteCollection()->get($name)->compile()->getPathVariables() as $variable) {
            $parameters[$variable] = $variable === 'userId' ? self::USER_ID : str_repeat('0', 64);
        }

        return $router->generate($name, $parameters);
    }

    /**
     * get_vehicle-acquisition → vehicleAcquisitionId=<premier id de vehicle_acquisition>
     *
     * @throws DoctrineDBALException
     */
    private function getIdParameter(string $name): string
    {
        $subject = str_replace('-', '_', substr($name, 4));
        $table   = $subject;

        return lcfirst(str_replace('_', '', ucwords($subject, '_'))) . 'Id=' . (int)$this->fetchOne('SELECT MIN(id) FROM `' . $table . '`');
    }
}

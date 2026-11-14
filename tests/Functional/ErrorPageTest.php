<?php

namespace App\Tests\Functional;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

class ErrorPageTest extends AppWebTestCase
{
    public function testUnknownPageIsANotFoundForAnonymous(): void
    {
        $this->client->request('GET', '/page-inexistante');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUnknownPageIsANotFoundForAdmin(): void
    {
        $this->loginAs(1, 'ROLE_ADMIN');

        $this->client->request('GET', '/page-inexistante');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * La page d'erreur de production (désactivée en debug) s'affiche pour chaque code, sans détail technique pour un visiteur
     */
    public function testErrorTemplateIsRenderedForEveryStatus(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/page-inexistante');
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);

        foreach ([403, 404, 418, 500] as $statusCode) {
            $html = static::getContainer()->get(Environment::class)->render(
                'bundles/TwigBundle/Exception/error.html.twig', ['exception' => new Exception('Détail secret'), 'status_code' => $statusCode]
            );

            $this->assertStringContainsString('<p class="error-code">' . $statusCode . '</p>', $html);
            $this->assertStringNotContainsString('Détail secret', $html, $statusCode . ' : un visiteur ne doit pas voir le message technique');
            $this->assertStringNotContainsString('error_page_', $html, $statusCode . ' : traduction manquante');
        }
    }
}

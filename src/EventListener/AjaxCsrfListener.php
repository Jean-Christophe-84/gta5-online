<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
readonly class AjaxCsrfListener
{
    const array EXCLUDED_ROUTES = ['login', 'switch_locale'];
    const string TOKEN_ID       = 'ajax';

    public function __construct(private ConfigurationHelper $configurationHelper, private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    /**
     * @throws DoctrineDBALException
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->isMethod('POST') || in_array($request->attributes->get('_route'), self::EXCLUDED_ROUTES, true)) {
            return;
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, (string)$request->headers->get('X-CSRF-Token')))) {
            $event->setResponse(
                new JsonResponse(
                    ['message' => ucfirst($this->configurationHelper->getTranslateLocale('invalid_csrf_token')), 'success' => false],
                    Response::HTTP_FORBIDDEN
                )
            );
        }
    }
}

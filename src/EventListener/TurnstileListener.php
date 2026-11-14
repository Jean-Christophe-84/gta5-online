<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Turnstile sur le formulaire de connexion : après le throttling (priorité 2080), avant le chargement de l'utilisateur et le mot de passe,
 * pour que la réponse ne dépende jamais de l'existence du compte. L'échec passe par LoginFailureEvent (journalisé et compté par le throttling)
 */
#[AsEventListener(event: CheckPassportEvent::class, method: 'onCheckPassport', priority: 2048)]
readonly class TurnstileListener
{
    public function __construct(private ConfigurationHelper $configurationHelper, private RequestStack $requestStack)
    {
    }

    /**
     * @throws DoctrineDBALException
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || $request->attributes->get('_route') !== 'login' || !$event->getPassport()->hasBadge(PasswordCredentials::class)) {
            return;
        }

        if (!$this->configurationHelper->checkTurnstile((string)$request->request->get('cf-turnstile-response'), $request->getClientIp())) {
            throw new CustomUserMessageAuthenticationException(ucfirst($this->configurationHelper->getTranslateLocale('the_anti_robot_check_failed')) . '.');
        }
    }
}

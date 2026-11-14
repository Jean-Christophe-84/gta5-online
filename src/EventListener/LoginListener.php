<?php

namespace App\EventListener;

use App\Entity\LoginError;
use App\Entity\LoginSuccess;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Journal des connexions via le formulaire : ni « rester connecté », ni la connexion automatique après validation du mail ou changement de mot de passe.
 * Il ne déclenche pas à lui seul de sauvegarde de la base
 */
#[AsEventListener(event: LoginFailureEvent::class, method: 'onLoginFailure')]
#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
readonly class LoginListener
{
    const int MAX_MAIL_LENGTH = 256;

    public function __construct(private BackupListener $backupListener, private EntityManagerInterface $entityManager)
    {
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'login') {
            return;
        }

        $loginError = new LoginError()
            ->setEmail(mb_substr(trim((string)$request->request->get('_username')), 0, self::MAX_MAIL_LENGTH))
            ->setDate()
            ->setIp((string)$request->getClientIp());

        $this->entityManager->persist($loginError);
        $this->backupListener->ignore(fn() => $this->entityManager->flush());
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($event->getRequest()->attributes->get('_route') !== 'login' || !$user instanceof User) {
            return;
        }

        $loginSuccess = new LoginSuccess()->setUser($user)->setDate()->setIp((string)$event->getRequest()->getClientIp());

        $this->entityManager->persist($loginSuccess);
        $this->backupListener->ignore(fn() => $this->entityManager->flush());
    }
}

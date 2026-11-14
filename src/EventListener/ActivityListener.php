<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\AfterResponse;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Date de dernière activité de l'utilisateur connecté, à chaque requête (pages et appels AJAX) :
 * en SQL direct (ne touche ni updated_at ni updated_by) et après la réponse (aucune attente pour l'utilisateur),
 * sans déclencher à elle seule de sauvegarde de la base
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
readonly class ActivityListener
{
    const int REFRESH_DELAY = 60; // Secondes minimum entre deux écritures

    public function __construct(
        private AfterResponse $afterResponse, private BackupListener $backupListener, private EntityManagerInterface $entityManager, private Security $security
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $user = $this->security->getUser();

        if (!$event->isMainRequest() || !$user instanceof User
            || ($user->getLastActivity() && $user->getLastActivity() > new DateTime('- ' . self::REFRESH_DELAY . ' seconds'))
        ) {
            return;
        }

        $userId = $user->id;

        $this->afterResponse->add(fn() => $this->backupListener->ignore(
            fn() => $this->entityManager->getConnection()->executeStatement(
                'UPDATE user SET last_activity = :lastActivity WHERE id = :userId', ['lastActivity' => date('Y-m-d H:i:s'), 'userId' => $userId]
            )
        ));
    }
}

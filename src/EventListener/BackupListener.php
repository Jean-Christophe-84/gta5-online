<?php

namespace App\EventListener;

use App\Helper\BackupHelper;
use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Sauvegarde la base une fois la réponse envoyée (ou la commande terminée) quand BackupMiddleware a vu une modification des données :
 * toute écriture, présente ou future, est couverte sans appel dans les contrôleurs et sans ralentir la réponse.
 */
class BackupListener
{
    /** Commandes qui recréent des données de référence : leur résultat ne doit jamais remplacer la sauvegarde */
    public const array SKIPPED_COMMANDS = ['RemoveOldLoginsCommand', 'UserStatisticCommand'];

    private bool $isPending = false;

    /**
     * @param Closure(): BackupHelper $backupHelper Chargé à la demande : le helper dépend de la connexion, qui dépend du middleware, qui dépend de ce listener
     */
    public function __construct(
        #[AutowireServiceClosure(BackupHelper::class)] private readonly Closure $backupHelper, private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Après tous les autres listeners : ceux qui écrivent eux aussi après la réponse (AfterResponse…) sont inclus dans la sauvegarde
     */
    #[AsEventListener(event: KernelEvents::TERMINATE, priority: -2048)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE, priority: -2048)]
    public function onTerminate(TerminateEvent|ConsoleTerminateEvent $event): void
    {
        if (!$this->isPending) {
            return;
        }
        $this->isPending = false;

        if ($event instanceof ConsoleTerminateEvent && in_array($event->getCommand()?->getName(), self::SKIPPED_COMMANDS, true)) {
            return;
        }

        try {
            ($this->backupHelper)()->backup();
        } catch (Throwable $throwable) {
            // La réponse est déjà partie : l'échec ne peut qu'être journalisé (var/log)
            $this->logger->critical('La sauvegarde de la base a échoué : ' . $throwable->getMessage(), ['exception' => $throwable]);
        }
    }

    /**
     * Exécute une écriture de suivi (dernière activité, journal des connexions…) qui ne justifie pas à elle seule une sauvegarde
     */
    public function ignore(callable $write): mixed
    {
        $isPending = $this->isPending;

        try {
            return $write();
        } finally {
            $this->isPending = $isPending;
        }
    }

    public function schedule(): void
    {
        $this->isPending = true;
    }
}

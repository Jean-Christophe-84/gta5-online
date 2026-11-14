<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Exécute des tâches après l'envoi de la réponse : la durée d'une requête ne doit pas révéler si un compte existe
 * (recherche du compte, création, envoi de mail...)
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
class AfterResponse
{
    /** @var callable[] */
    private array $tasks = [];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function add(callable $task): void
    {
        $this->tasks[] = $task;
    }

    public function __invoke(): void
    {
        foreach ($this->tasks as $task) {
            try {
                $task();
            } catch (Throwable $exception) {
                $this->logger->error('A task executed after the response failed', ['exception' => $exception]);
            }
        }

        $this->tasks = [];
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Modale de suppression : le formulaire d'édition en lecture seule, précédé de ce qui bloque la suppression ou de ce qu'elle entraîne
 */
readonly class DeletionResponder
{
    public function __construct(private Environment $twig)
    {
    }

    /**
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function json(string $body, array $deletion): JsonResponse
    {
        return new JsonResponse(
            [
                'deletable' => empty($deletion['blockers']),
                'success'   => true,
                'template'  => $this->twig->render(
                    '_partial/deletion.html.twig',
                    ['blockers' => $deletion['blockers'], 'body' => $body, 'warnings' => $deletion['warnings'] ?? []]
                )
            ]
        );
    }
}

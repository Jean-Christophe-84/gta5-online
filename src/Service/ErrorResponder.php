<?php

declare(strict_types=1);

namespace App\Service;

use App\Helper\ConfigurationHelper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

readonly class ErrorResponder
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private LoggerInterface $logger, private Security $security,
        #[Autowire('%kernel.project_dir%/src/')] private string $sourceDir
    ) {
    }

    public function getMessage(Throwable $exception, string $message = ''): string
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return $message;
        }

        [$file, $line] = $this->getSourceLocation($exception);

        return $this->configurationHelper->getTranslateLocale(
            'error_technical_details', ['file' => $file, 'line' => $line, 'message' => $exception->getMessage()]
        );
    }

    public function json(Throwable $exception, string $message): JsonResponse
    {
        $this->logger->error($message, ['exception' => $exception]);

        return new JsonResponse(
            ['message' => ucfirst($this->getMessage($exception, $message)), 'success' => false],
            $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @return array{string, int}
     */
    private function getSourceLocation(Throwable $exception): array
    {
        if (str_starts_with($exception->getFile(), $this->sourceDir)) {
            return [$exception->getFile(), $exception->getLine()];
        }

        foreach ($exception->getTrace() as $trace) {
            if (isset($trace['file'], $trace['line']) && str_starts_with($trace['file'], $this->sourceDir)) {
                return [$trace['file'], $trace['line']];
            }
        }

        return [$exception->getFile(), $exception->getLine()];
    }
}

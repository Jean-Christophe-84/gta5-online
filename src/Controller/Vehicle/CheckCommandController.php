<?php

namespace App\Controller\Vehicle;

use App\Entity\Configuration;
use App\Entity\Vehicle;
use App\Helper\ConfigurationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CheckCommandController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/vehicle/check-command', name: 'check_command_vehicle')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function checkCommand(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_CONFIGURATION)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $projectDir = $this->getParameter('kernel.project_dir');

        $processIdFile = $projectDir . '/var/run/vehicles_get.pid';
        $exitFile      = $projectDir . '/var/run/vehicles_get.exit';
        $logFile       = $projectDir . '/var/log/vehicles_get.log';

        $configuration = $entityManager->getRepository(Configuration::class)->find(1);

        $running = false;

        if (file_exists($processIdFile)) {
            $processId = trim((string) file_get_contents($processIdFile));

            if ($processId !== '' && $this->isProcessRunning((int) $processId)) {
                $running = true;
            }
        }

        if (!$running && file_exists($exitFile)) {
            $exitCode = (int) trim((string) file_get_contents($exitFile));

            unlink($exitFile);

            $configuration->setPercentageVehicles(0);

            $entityManager->persist($configuration);
            $entityManager->flush();

            if ($exitCode !== 0) {
                return new JsonResponse(
                    [
                        'error'               => $this->getLastLogLines($logFile),
                        'exitCode'            => $exitCode,
                        'message'             => ucfirst($configurationHelper->getTranslateLocale('command_finished_with_error', ['command' => 'vehicles'])),
                        'percentage'          => $configuration->getPercentageVehicles(),
                        'percentageFormatted' => $configurationHelper->formatPercent($configuration->getPercentageVehicles(), 2, true),
                        'running'             => false,
                        'success'             => false,
                    ]
                );
            }

            return new JsonResponse(
                [
                    'message'             => ucfirst($configurationHelper->getTranslateLocale('command_finished_with_success', ['command' => 'vehicles'])),
                    'percentage'          => $configuration->getPercentageVehicles(),
                    'percentageFormatted' => $configurationHelper->formatPercent($configuration->getPercentageVehicles(), 2, true),
                    'running'             => false,
                    'success'             => true,
                ]
            );
        }

        return new JsonResponse(
            [
                'message'    => ucfirst(
                    $configurationHelper->getTranslateLocale($running ? 'command_running' : 'command_none_running', ['command' => 'vehicles'])
                ),
                'percentage'          => $configuration->getPercentageVehicles(),
                'percentageFormatted' => $configurationHelper->formatPercent($configuration->getPercentageVehicles(), 2, true),
                'running'             => $running,
                'success'             => true,
            ]
        );
    }

    private function isProcessRunning(int $processId): bool
    {
        if ($processId <= 0) {
            return false;
        }

        $process = new Process(['kill', '-0', (string) $processId]);
        $process->run();

        return $process->isSuccessful();
    }

    private function getLastLogLines(string $logFile, int $lines = 30): string
    {
        if (!file_exists($logFile)) {
            return '';
        }

        $content = file($logFile, FILE_IGNORE_NEW_LINES);

        if ($content === false) {
            return '';
        }

        return implode("\n", array_slice($content, -$lines));
    }
}

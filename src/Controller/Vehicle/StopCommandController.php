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

class StopCommandController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/vehicle/stop-command', name: 'stop_command_vehicle', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function stopCommand(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted(Vehicle::PERMISSION_VEHICLE_CONFIGURATION)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $projectDir    = $this->getParameter('kernel.project_dir');
        $processIdFile = $projectDir . '/var/run/vehicles_get.pid';

        $configuration = $entityManager->getRepository(Configuration::class)->find(1);
        $configuration->setPercentageVehicles(0);

        $entityManager->persist($configuration);
        $entityManager->flush();

        if (!file_exists($processIdFile)) {
            return new JsonResponse(
                [
                    'message' => ucfirst($configurationHelper->getTranslateLocale('command_none_to_stop', ['command' => 'vehicles'])),
                    'success' => false
                ]
            );
        }

        $processId = trim((string)file_get_contents($processIdFile));

        if ($processId === '' || !$this->isProcessRunning((int)$processId)) {
            unlink($processIdFile);

            return new JsonResponse(
                [
                    'message' => ucfirst($configurationHelper->getTranslateLocale('command_no_longer_running', ['command' => 'vehicles'])),
                    'success' => false
                ]
            );
        }

        $process = new Process(['kill', $processId]);
        $process->run();

        if ($process->isSuccessful()) {
            unlink($processIdFile);

            return new JsonResponse(
                [
                    'message' => ucfirst($configurationHelper->getTranslateLocale('command_stopped', ['command' => 'vehicles'])),
                    'success' => true
                ]
            );
        }

        return new JsonResponse(
            [
                'message' => ucfirst($configurationHelper->getTranslateLocale('command_stop_failed', ['command' => 'vehicles'])),
                'success' => false
            ]
        );
    }

    private function isProcessRunning(int $processId): bool
    {
        if ($processId <= 0) {
            return false;
        }

        $process = new Process(['kill', '-0', (string)$processId]);
        $process->run();

        return $process->isSuccessful();
    }
}

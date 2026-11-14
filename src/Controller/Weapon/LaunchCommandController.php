<?php

namespace App\Controller\Weapon;

use App\Entity\Configuration;
use App\Entity\Weapon;
use App\Helper\ConfigurationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LaunchCommandController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/weapon/launch-command', name: 'launch_command_weapon', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function launchCommand(ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted(Weapon::PERMISSION_WEAPON_CONFIGURATION)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $phpBinary  = new PhpExecutableFinder()->find() ?: 'php';

        $runDir        = $projectDir . '/var/run';
        $logFile       = $projectDir . '/var/log/weapons_get.log';
        $processIdFile = $runDir . '/weapons_get.pid';
        $exitFile      = $runDir . '/weapons_get.exit';

        $filesystem = new Filesystem();
        $filesystem->mkdir($runDir);

        if (file_exists($exitFile)) {
            unlink($exitFile);
        }

        file_put_contents($logFile, '');

        $process = Process::fromShellCommandline(
            sprintf(
                'cd %s && (%s bin/console weapons:get >> %s 2>&1; echo $? > %s) & echo $! > %s',
                escapeshellarg($projectDir),
                escapeshellarg($phpBinary),
                escapeshellarg($logFile),
                escapeshellarg($exitFile),
                escapeshellarg($processIdFile)
            )
        );

        'Y-m-d H:i:s'
        |> date(...)
        |> (fn($date) => sprintf("[%s] weapons:get launched\n", $date))
        |> (fn($line) => file_put_contents($projectDir . '/var/log/commands_history.log', $line, FILE_APPEND));

        $process->setTimeout(null);
        $process->run();

        $configuration = $entityManager->getRepository(Configuration::class)->find(1);
        $configuration->setPercentageWeapons(1);

        $entityManager->persist($configuration);
        $entityManager->flush();

        return new JsonResponse(
            [
                'message' => ucfirst($configurationHelper->getTranslateLocale('command_launched', ['command' => 'weapons'])),
                'success' => true
            ]
        );
    }
}

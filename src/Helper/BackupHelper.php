<?php

namespace App\Helper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sauvegarde de la base de la connexion Doctrine (db_test en environnement de test) dans le dossier backup_directory :
 * - "{backup_name}.sql" : toujours le dernier état, non compressé (source de make reset) ;
 * - "history/{backup_name}_Y-m-d_H-i-s.sql.gz" : chaque état différent du précédent, élagué par prune() selon RETENTION.
 */
readonly class BackupHelper
{
    public const string DATE_FORMAT = 'Y-m-d_H-i-s';

    /** Tout est gardé sur 48 h, puis le dernier de chaque jour sur 30 jours, puis le dernier de chaque mois */
    public const array RETENTION = ['all' => '-48 hours', 'daily' => '-30 days'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%backup_directory%')] private string $directory,
        #[Autowire('%backup_name%')] private string $name
    ) {
    }

    /**
     * @return string|null Le fichier ajouté à l'historique, null si la base n'a pas changé depuis la dernière sauvegarde
     * @throws Exception
     */
    public function backup(DateTimeImmutable $now = new DateTimeImmutable()): ?string
    {
        $parameters = $this->entityManager->getConnection()->getParams();
        $dsn        = 'mysql:host=' . $parameters['host'] . ';port=' . ($parameters['port'] ?? 3306) . ';dbname=' . $parameters['dbname'];
        $latest     = $this->getLatestPath();
        $temporary  = $this->getTemporaryPath($latest);

        if (!is_dir($this->getHistoryDirectory()) && !mkdir($this->getHistoryDirectory(), 0777, true) && !is_dir($this->getHistoryDirectory())) {
            throw new RuntimeException('Impossible de créer le dossier "' . $this->getHistoryDirectory() . '"');
        }

        try {
            // Sans la date du dump, deux états identiques de la base donnent deux fichiers identiques
            new Mysqldump($dsn, $parameters['user'], $parameters['password'] ?? '', ['default-character-set' => Mysqldump::UTF8MB4, 'skip-dump-date' => true])
                ->start($temporary);

            if (is_file($latest) && md5_file($latest) === md5_file($temporary) && $this->getHistory() !== []) {
                return null;
            }

            $file = $this->getHistoryDirectory() . '/' . $this->name . '_' . $now->format(self::DATE_FORMAT) . '.sql.gz';
            $this->write($file, (string)gzencode((string)file_get_contents($temporary), 9));

            // Le renommage est atomique : le dernier état n'est jamais un fichier à moitié écrit
            if (!rename($temporary, $latest)) {
                throw new RuntimeException('Impossible d\'écrire "' . $latest . '"');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        $this->prune($now);

        return $file;
    }

    /**
     * @return string[] Les fichiers de l'historique, du plus ancien au plus récent
     */
    public function getHistory(): array
    {
        $files = glob($this->getHistoryDirectory() . '/' . $this->name . '_*.sql.gz') ? : [];
        sort($files);

        return $files;
    }

    public function getHistoryDirectory(): string
    {
        return $this->directory . '/history';
    }

    public function getLatestPath(): string
    {
        return $this->directory . '/' . $this->name . '.sql';
    }

    /**
     * @return string[] Les fichiers supprimés
     */
    public function prune(DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        $deleted = [];
        $kept    = [];

        foreach (array_reverse($this->getHistory()) as $file) {
            $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, substr(basename($file), strlen($this->name) + 1, 19));

            if (!$date || $date > $now->modify(self::RETENTION['all'])) {
                continue;
            }

            // Du plus récent au plus ancien : le premier fichier vu dans un jour (ou un mois) est le dernier de cette période
            $period = $date > $now->modify(self::RETENTION['daily']) ? $date->format('Y-m-d') : $date->format('Y-m');

            if (isset($kept[$period])) {
                unlink($file);
                $deleted[] = $file;
            } else {
                $kept[$period] = true;
            }
        }

        return array_reverse($deleted);
    }

    private function getTemporaryPath(string $path): string
    {
        return $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    }

    private function write(string $path, string $content): void
    {
        $temporary = $this->getTemporaryPath($path);

        if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Impossible d\'écrire "' . $path . '"');
        }
    }
}

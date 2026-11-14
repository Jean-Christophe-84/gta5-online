<?php

namespace App\Doctrine;

use App\EventListener\BackupListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Note toute modification des données sur la connexion Doctrine, par l'ORM comme en SQL brut : BackupListener sauvegarde alors la base
 * en fin de requête ou de commande. Seule une écriture exécutée sans erreur et qui a modifié au moins une ligne compte. Le DDL n'en fait pas
 * partie, pour que le CREATE DATABASE de make reset ne sauvegarde jamais une base vide. Chargé seulement en dev et en test : jamais de
 * sauvegarde en production.
 */
#[AsMiddleware]
#[When(env: 'dev')]
#[When(env: 'test')]
readonly class BackupMiddleware implements Middleware
{
    private const string TRUNCATE = '/^\s*TRUNCATE\b/i';
    private const string WRITE    = '/^\s*\(?\s*(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b/i';

    public function __construct(private BackupListener $backupListener)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->backupListener) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly BackupListener $backupListener)
            {
                parent::__construct($driver);
            }

            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params), $this->backupListener) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly BackupListener $backupListener)
                    {
                        parent::__construct($connection);
                    }

                    public function exec(string $sql): int|string
                    {
                        $count = parent::exec($sql);

                        if (BackupMiddleware::hasChanged($sql, $count)) {
                            $this->backupListener->schedule();
                        }

                        return $count;
                    }

                    public function prepare(string $sql): Statement
                    {
                        if (!BackupMiddleware::isWrite($sql)) {
                            return parent::prepare($sql);
                        }

                        return new class(parent::prepare($sql), $sql, $this->backupListener) extends AbstractStatementMiddleware {
                            public function __construct(Statement $statement, private readonly string $sql, private readonly BackupListener $backupListener)
                            {
                                parent::__construct($statement);
                            }

                            public function execute(): Result
                            {
                                $result = parent::execute();

                                if (BackupMiddleware::hasChanged($this->sql, $result->rowCount())) {
                                    $this->backupListener->schedule();
                                }

                                return $result;
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $result = parent::query($sql);

                        if (BackupMiddleware::hasChanged($sql, $result->rowCount())) {
                            $this->backupListener->schedule();
                        }

                        return $result;
                    }
                };
            }
        };
    }

    /**
     * Une écriture qui a modifié au moins une ligne (TRUNCATE en annonce toujours 0)
     */
    public static function hasChanged(string $sql, int|string $count): bool
    {
        return self::isWrite($sql) && ((int)$count > 0 || preg_match(self::TRUNCATE, $sql));
    }

    public static function isWrite(string $sql): bool
    {
        return (bool)preg_match(self::WRITE, $sql);
    }
}

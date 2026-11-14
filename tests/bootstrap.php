<?php

use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(__DIR__.'/../.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$database = parse_url($_SERVER['DATABASE_URL']);
$dbName   = ltrim($database['path'], '/') . '_test';
$command  = sprintf(
    'mysql -h %s -P %d -u%s -p%s',
    escapeshellarg($database['host']), $database['port'] ?? 3306, escapeshellarg($database['user']), escapeshellarg($database['pass'])
);

passthru($command . ' -e ' . escapeshellarg('DROP DATABASE IF EXISTS `' . $dbName . '`; CREATE DATABASE `' . $dbName . '`') . ' 2>/dev/null');
passthru($command . ' ' . escapeshellarg($dbName) . ' < ' . escapeshellarg(__DIR__ . '/../backup/gta5_online.sql') . ' 2>/dev/null');

// Le throttling de connexion persiste dans le cache : on repart de zéro pour ne pas cumuler les échecs des lancements précédents
passthru('php ' . escapeshellarg(__DIR__ . '/../bin/console') . ' cache:pool:clear cache.rate_limiter --env=test --quiet');

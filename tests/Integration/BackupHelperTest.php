<?php

namespace App\Tests\Integration;

use App\Helper\BackupHelper;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class BackupHelperTest extends KernelTestCase
{
    protected function setUp(): void
    {
        new Filesystem()->remove(static::getContainer()->getParameter('backup_directory'));
    }

    public function testBackupWritesTheLatestStateAndTheHistory(): void
    {
        $helper = $this->getHelper();

        $file = $helper->backup(new DateTimeImmutable('2026-11-13 14:32:05'));

        $this->assertStringEndsWith('/var/backup_test/gta5_online.sql', $helper->getLatestPath());
        $this->assertSame($helper->getHistoryDirectory() . '/gta5_online_2026-11-13_14-32-05.sql.gz', $file);
        $this->assertSame([$file], $helper->getHistory());

        $latest = file_get_contents($helper->getLatestPath());
        $this->assertStringContainsString('CREATE TABLE `vehicle`', $latest);
        $this->assertStringContainsString('Database: db_test', $latest);
        $this->assertStringNotContainsString('-- Date:', $latest);
        $this->assertSame($latest, gzdecode(file_get_contents($file)));
        $this->assertSame([], glob(dirname($helper->getLatestPath()) . '/*.tmp'));
        $this->assertSame([], glob($helper->getHistoryDirectory() . '/*.tmp'));
    }

    public function testUnchangedDatabaseAddsNothingToTheHistory(): void
    {
        $helper = $this->getHelper();
        $helper->backup(new DateTimeImmutable('2026-11-13 14:32:05'));

        $this->assertNull($helper->backup(new DateTimeImmutable('2026-11-13 14:40:00')));
        $this->assertCount(1, $helper->getHistory());
        $this->assertSame([], glob(dirname($helper->getLatestPath()) . '/*.tmp'));
    }

    public function testChangedDatabaseIsAddedToTheHistory(): void
    {
        $helper = $this->getHelper();
        $helper->backup(new DateTimeImmutable('2026-11-13 14:32:05'));
        file_put_contents($helper->getLatestPath(), "-- état précédent\n", FILE_APPEND);

        $file = $helper->backup(new DateTimeImmutable('2026-11-13 14:40:00'));

        $this->assertSame($helper->getHistoryDirectory() . '/gta5_online_2026-11-13_14-40-00.sql.gz', $file);
        $this->assertCount(2, $helper->getHistory());
        $this->assertStringNotContainsString('-- état précédent', file_get_contents($helper->getLatestPath()));
    }

    public function testEmptyHistoryIsFilledEvenWhenTheLatestStateIsUnchanged(): void
    {
        $helper = $this->getHelper();
        $helper->backup(new DateTimeImmutable('2026-11-13 14:32:05'));
        unlink($helper->getHistory()[0]);

        $this->assertNotNull($helper->backup(new DateTimeImmutable('2026-11-13 14:40:00')));
        $this->assertCount(1, $helper->getHistory());
    }

    public function testPruneKeepsEverythingFor48HoursThenOnePerDayFor30DaysThenOnePerMonth(): void
    {
        $helper = $this->getHelper();
        $kept   = [
            '2026-11-13_11-00-00',
            '2026-11-11_13-00-00',
            '2026-11-11_12-30-00',
            '2026-11-11_09-00-00',
            '2026-11-05_23-00-00',
            '2026-10-15_10-00-00',
            '2026-10-10_18-00-00',
            '2026-09-30_08-00-00',
            '2025-01-15_20-00-00',
            'inconnu'
        ];
        $deleted = ['2026-11-11_08-00-00', '2026-11-05_10-00-00', '2026-10-02_08-00-00', '2026-09-01_08-00-00', '2025-01-03_08-00-00'];

        mkdir($helper->getHistoryDirectory(), 0777, true);
        foreach ([...$kept, ...$deleted] as $date) {
            touch($helper->getHistoryDirectory() . '/gta5_online_' . $date . '.sql.gz');
        }

        $result = $helper->prune(new DateTimeImmutable('2026-11-13 12:00:00'));

        $this->assertEqualsCanonicalizing($this->getPaths($deleted), $result);
        $this->assertEqualsCanonicalizing($this->getPaths($kept), $helper->getHistory());
    }

    private function getHelper(): BackupHelper
    {
        return static::getContainer()->get(BackupHelper::class);
    }

    private function getPaths(array $dates): array
    {
        return array_map(fn(string $date) => $this->getHelper()->getHistoryDirectory() . '/gta5_online_' . $date . '.sql.gz', $dates);
    }
}

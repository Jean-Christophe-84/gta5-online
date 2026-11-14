<?php

namespace App\Tests\Unit;

use App\Doctrine\BackupMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BackupMiddlewareTest extends TestCase
{
    public static function provideQueries(): array
    {
        return [
            ['INSERT INTO vehicle (name) VALUES (?)', 1, true],
            ["\n            update vehicle SET name = ?", 3, true],
            ['DELETE FROM vehicle WHERE id = ?', 1, true],
            ['REPLACE INTO vehicle (id, name) VALUES (?, ?)', 2, true],
            ['(INSERT INTO vehicle (name) VALUES (?))', 1, true],
            ['TRUNCATE vehicle', 0, true],
            ['UPDATE vehicle SET name = name', 0, false],
            ['INSERT INTO vehicle_user SELECT 1 FROM vehicle WHERE NOT EXISTS (SELECT 1)', '0', false],
            ['SELECT * FROM vehicle', 12, false],
            ['CREATE DATABASE `db`', 1, false],
            ['DROP DATABASE `db`', 0, false],
            ['ALTER TABLE vehicle ADD color VARCHAR(7)', 0, false],
            ['SET NAMES utf8mb4', 0, false],
            ['SAVEPOINT DOCTRINE_2', 0, false]
        ];
    }

    #[DataProvider('provideQueries')]
    public function testOnlyAWriteChangingRowsCounts(string $sql, int|string $count, bool $expected): void
    {
        $this->assertSame($expected, BackupMiddleware::hasChanged($sql, $count));
    }
}
